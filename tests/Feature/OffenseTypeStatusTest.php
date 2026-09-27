<?php

namespace Tests\Feature;

use App\Models\{AuditLog, Citation, User, Violation, ViolationType, Violator};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OffenseTypeStatusTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role): User
    {
        return User::create(['name' => $role, 'username' => $role, 'email' => $role.'@example.test', 'password' => 'password', 'role' => $role]);
    }

    private function input(ViolationType $type): array
    {
        return ['top_number' => 'TOP-STATUS-1', 'full_name' => 'Test Citizen', 'address' => 'Luna', 'location' => 'Checkpoint', 'confiscated_id' => 'None', 'violation_type_id' => $type->id];
    }

    public function test_admin_can_deactivate_and_reactivate_without_deleting_history(): void
    {
        $admin = $this->account('admin');
        $enforcer = $this->account('enforcer');
        $type = ViolationType::create(['offense_name' => 'Status test offense', 'category' => 'Traffic Violation', 'fine_amount' => 500]);
        $this->assertTrue($type->fresh()->is_active);
        $person = Violator::create(['full_name' => 'Historical Person']);
        $record = Violation::create(['violator_id' => $person->id, 'officer_id' => $enforcer->id, 'violation_type_id' => $type->id, 'violation_date' => today()]);
        Citation::create(['violation_id' => $record->id, 'ticket_no' => 'HISTORY-1', 'fine_amount' => 500, 'payment_status' => 'pending', 'due_date' => today()->addDays(15)]);
        $this->actingAs($admin)->get(route('admin.violation-types'))->assertOk()->assertSee('Save status')->assertDontSee('>Delete</button>', false);
        $this->patch(route('admin.violation-types.status', $type), ['is_active' => 0])->assertRedirect(route('admin.violation-types'));
        $this->assertFalse($type->fresh()->is_active);
        $this->assertFalse($type->fresh()->trashed());
        $this->assertSame('Status test offense', $record->fresh()->violationType->offense_name);
        $this->assertEquals(500, $record->citation->fine_amount);
        $this->assertTrue(AuditLog::where('module', 'violation_types')->where('details', 'like', '%Inactive%')->exists());
        $this->get(route('admin.violation-types'))->assertSee('Status test offense')->assertSee('Inactive');
        $this->get(route('reports.period', ['type' => $type->id]))->assertViewHas('records', fn ($rows) => $rows->total() === 1);
        $this->actingAs($enforcer)->get(route('enforcer.create'))->assertDontSee('Status test offense');
        $this->post(route('enforcer.preview'), $this->input($type))->assertSessionHasErrors('violation_type_id');
        $this->actingAs($admin)->patch(route('admin.violation-types.status', $type), ['is_active' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($type->fresh()->is_active);
        $this->actingAs($enforcer)->get(route('enforcer.create'))->assertSee('Status test offense');
        $this->post(route('enforcer.preview'), $this->input($type))->assertRedirect(route('enforcer.review'));
    }

    public function test_deactivation_blocks_an_already_reviewed_submission(): void
    {
        $admin = $this->account('admin');
        $enforcer = $this->account('enforcer');
        $type = ViolationType::firstOrFail();
        $this->actingAs($enforcer)->post(route('enforcer.preview'), $this->input($type))->assertRedirect(route('enforcer.review'));
        $token = session('enforcer_preview.token');
        $this->actingAs($admin)->patch(route('admin.violation-types.status', $type), ['is_active' => 0])->assertSessionHasNoErrors();
        $this->actingAs($enforcer)->post(route('enforcer.confirm'), ['preview_token' => $token, 'confirmed' => 1])->assertSessionHasErrors('preview');
        $this->assertDatabaseCount('violations', 0);
        $this->assertDatabaseCount('citations', 0);
        $this->assertDatabaseCount('violators', 0);
    }

    public function test_only_admins_can_change_status_and_invalid_status_is_rejected(): void
    {
        $type = ViolationType::firstOrFail();
        $url = route('admin.violation-types.status', $type);
        $this->patch($url, ['is_active' => 0])->assertRedirect(route('login'));
        $this->actingAs($this->account('enforcer'))->patch($url, ['is_active' => 0])->assertForbidden();
        $this->actingAs($this->account('admin'))->patch($url, [])->assertSessionHasErrors('is_active');
        $this->patch($url, ['is_active' => 'invalid'])->assertSessionHasErrors('is_active');
        $this->delete('/admin/violation-types/'.$type->id)->assertStatus(405);
        $this->assertTrue($type->fresh()->is_active);
        $this->assertFalse($type->fresh()->trashed());
    }

    public function test_editing_a_used_inactive_offense_preserves_status_and_original_details(): void
    {
        $this->actingAs($admin = $this->account('admin'));
        $type = ViolationType::firstOrFail();
        $originalName = $type->offense_name;
        $person = Violator::create(['full_name' => 'Historical Person']);
        $record = Violation::create(['violator_id' => $person->id, 'officer_id' => $admin->id, 'violation_type_id' => $type->id, 'violation_date' => today()]);
        $this->patch(route('admin.violation-types.status', $type), ['is_active' => 0])->assertSessionHasNoErrors();
        $this->put(route('admin.violation-types.update', $type), ['offense_name' => 'Amended inactive offense', 'category' => 'Traffic Violation', 'fine_amount' => 750])->assertSessionHasNoErrors();
        $new = ViolationType::where('offense_key', $type->offense_key)->firstOrFail();
        $this->assertFalse($new->is_active);
        $this->assertTrue($type->fresh()->trashed());
        $this->assertSame($originalName, $record->fresh()->violationType->offense_name);
        $this->patch(route('admin.violation-types.status', $type), ['is_active' => 1])->assertNotFound();
        $this->patch(route('admin.violation-types.status', $new), ['is_active' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($new->fresh()->is_active);
    }
}
