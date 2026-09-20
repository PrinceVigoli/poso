<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Citation;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationType;
use App\Models\Violator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'admin'): User
    {
        $number = User::count() + 1;
        return User::create(['name' => 'Test User', 'username' => "user$number", 'email' => "user$number@example.test", 'password' => 'password', 'role' => $role]);
    }

    private function citation(User $officer, string $ticket, string $status = 'pending'): Citation
    {
        $person = Violator::create(['full_name' => "Person $ticket"]);
        $type = ViolationType::firstOrCreate(['offense_name' => 'Test offense'], ['fine_amount' => 100]);
        $violation = Violation::create(['violator_id' => $person->id, 'officer_id' => $officer->id, 'violation_type_id' => $type->id, 'violation_date' => today(), 'location' => 'Test street', 'status' => $status === 'paid' ? 'settled' : 'pending']);
        return Citation::create(['violation_id' => $violation->id, 'ticket_no' => $ticket, 'fine_amount' => 100, 'due_date' => today()->subDay(), 'payment_status' => $status, 'paid_at' => $status === 'paid' ? today() : null]);
    }

    public function test_dashboard_refreshes_overdue_counts_and_shows_archive_shortcuts(): void
    {
        $officer = $this->user();
        $this->actingAs($officer);
        $overdue = $this->citation($officer, 'DUE-001');
        $this->citation($officer, 'PAID-001', 'paid');
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Review overdue verification')->assertSee('Active Violators (1)')->assertSee('Archived Violators (1)')
            ->assertViewHas('weekly', fn ($weekly) => $weekly->count() === 7 && $weekly->sum() === 2);
        $this->assertSame('overdue', $overdue->fresh()->effective_payment_status);
    }

    public function test_ticket_search_respects_payment_status(): void
    {
        $officer = $this->user();
        $this->actingAs($officer);
        $this->citation($officer, 'MATCH-UNPAID');
        $this->citation($officer, 'MATCH-PAID', 'paid');
        // The unpaid record is overdue, so its name legitimately appears in the
        // topbar notification panel. Scope the exclusion to the filtered list.
        $this->get(route('violations.index', ['search' => 'MATCH', 'payment_status' => 'paid']))->assertOk()
            ->assertSee('MATCH-PAID')
            ->assertViewHas('violations', fn ($rows) => $rows->pluck('citation.ticket_no')->all() === ['MATCH-PAID']);
    }

    public function test_repeated_payment_preserves_date_and_does_not_duplicate_audit(): void
    {
        $officer = $this->user('admin');
        $this->actingAs($officer);
        $citation = $this->citation($officer, 'PAY-001');
        $this->post(route('citations.pay', $citation), ['treasury_receipt_no' => 'OR-001', 'receipt_date' => today()->toDateString(), 'receipt_total' => 500, 'receipt_verified' => 1])->assertRedirect();
        $paidAt = $citation->fresh()->paid_at->toDateString();
        $this->travel(2)->days();
        $this->post(route('citations.pay', $citation), ['treasury_receipt_no' => 'OR-001', 'receipt_date' => today()->toDateString(), 'receipt_total' => 500, 'receipt_verified' => 1])->assertRedirect();
        $this->assertSame($paidAt, $citation->fresh()->paid_at->toDateString());
        $this->assertSame('settled', $citation->violation->fresh()->status);
        $this->assertSame(1, AuditLog::where('module', 'citations')->count());
        $this->travelBack();
    }

    public function test_deactivated_session_loses_access_including_enforcer_routes(): void
    {
        $user = $this->user('enforcer');
        $this->actingAs($user);
        $user->update(['is_active' => false]);
        $this->get(route('violations.create'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_logout_and_role_access(): void
    {
        $user = $this->user('enforcer');
        $this->post(route('login.submit'), ['username' => $user->username, 'password' => 'wrong'])->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->post(route('login.submit'), ['username' => $user->username, 'password' => 'password'])->assertRedirect(route('enforcer.create'));
        $this->get(route('dashboard'))->assertForbidden();
        $this->get(route('admin.users'))->assertForbidden();
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_add_violator_route_and_reports_are_accessible_and_dates_are_validated(): void
    {
        $this->actingAs($this->user('admin'));
        $this->get(route('violators.create'))->assertForbidden();
        $this->actingAs($this->user('admin'));
        foreach (['reports.violations', 'reports.summary'] as $route) {
            $this->get(route($route))->assertOk();
            $this->get(route($route, ['period' => 'yearly']))->assertSessionHasErrors('period');
            $this->get(route($route, ['date' => 'invalid']))->assertSessionHasErrors('date');
        }
    }

    public function test_last_active_administrator_cannot_be_deactivated(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin);
        $data = ['name' => $admin->name, 'username' => $admin->username, 'email' => $admin->email, 'role' => 'admin', 'is_active' => 0];
        $this->put(route('admin.users.update', $admin), $data)->assertSessionHasErrors('role');
        $this->assertTrue($admin->fresh()->is_active);
        $this->user('admin');
        $this->put(route('admin.users.update', $admin), $data)->assertRedirect(route('admin.users'));
        $this->assertFalse($admin->fresh()->is_active);
    }
}
