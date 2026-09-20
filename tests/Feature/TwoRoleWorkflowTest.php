<?php

namespace Tests\Feature;

use App\Models\{User, Violation, ViolationType, Violator};
use Tests\TestCase;

class TwoRoleWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // DDL conversion must run outside a transaction so SQLite can disable
        // foreign-key cascades while rebuilding the users table.
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate:fresh')->assertExitCode(0);
    }

    private function account(string $role): User
    {
        return User::create(['name' => $role, 'username' => $role, 'email' => $role.'@example.test', 'password' => 'password', 'role' => $role]);
    }

    public function test_officer_role_cannot_be_created_or_assigned(): void
    {
        $admin = $this->account('admin');
        $enforcer = $this->account('enforcer');
        $this->actingAs($admin)->get(route('admin.users.create'))->assertOk()->assertDontSee('value="officer"', false);
        $data = ['name' => 'New user', 'username' => 'newuser', 'email' => 'new@example.test', 'password' => 'password', 'password_confirmation' => 'password', 'role' => 'officer'];
        $this->post(route('admin.users.store'), $data)->assertSessionHasErrors('role');
        $this->put(route('admin.users.update', $enforcer), $data + ['is_active' => 1])->assertSessionHasErrors('role');
        $this->assertSame('enforcer', $enforcer->fresh()->role);
        $this->assertSame(2, User::count());
    }

    public function test_neither_role_can_edit_or_delete_saved_records(): void
    {
        $enforcer = $this->account('enforcer');
        $person = Violator::create(['full_name' => 'Preserved record']);
        $violation = Violation::create(['violator_id' => $person->id, 'officer_id' => $enforcer->id, 'violation_type_id' => ViolationType::firstOrFail()->id, 'violation_date' => today()]);
        foreach ([$enforcer, $this->account('admin')] as $user) {
            $this->actingAs($user);
            foreach (['violations' => $violation, 'violators' => $person] as $resource => $record) {
                $this->get(route($resource.'.edit', $record))->assertForbidden();
                $this->put(route($resource.'.update', $record), [])->assertForbidden();
                $this->delete(route($resource.'.destroy', $record))->assertForbidden();
            }
        }
        $this->assertDatabaseHas('violations', ['id' => $violation->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('violators', ['id' => $person->id, 'deleted_at' => null]);
    }

    public function test_migration_converts_existing_officers_and_preserves_authorship(): void
    {
        $migration = require database_path('migrations/2026_09_10_000003_retire_officer_role.php');
        $migration->down();
        $user = $this->account('officer');
        $person = Violator::create(['full_name' => 'Historical record']);
        $violation = Violation::create(['violator_id' => $person->id, 'officer_id' => $user->id, 'violation_type_id' => ViolationType::firstOrFail()->id, 'violation_date' => today()]);
        $migration->up();
        $this->assertSame('enforcer', $user->fresh()->role);
        $this->assertSame($user->id, $violation->fresh()->officer_id);
        $this->assertDatabaseHas('audit_logs', ['module' => 'users', 'action' => 'updated']);
        $this->actingAs($user->fresh())->get(route('enforcer.create'))->assertOk();
        $this->get(route('dashboard'))->assertForbidden();
    }
}
