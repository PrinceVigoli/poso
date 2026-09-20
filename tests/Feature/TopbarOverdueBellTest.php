<?php

namespace Tests\Feature;

use App\Models\{User, Violator, Violation, ViolationType, Citation};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopbarOverdueBellTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role): User
    {
        $n = User::count();
        return User::create(['name' => 'Bell '.$role.' '.$n, 'username' => $role.$n, 'email' => $role.$n.'@example.test', 'password' => 'original-test-password', 'role' => $role]);
    }

    private function record(?int $fine = 500): Violation
    {
        $person = Violator::create(['full_name' => 'Bell Citizen '.(Violator::count() + 1), 'address' => 'Incident address']);
        $type = ViolationType::create(['offense_name' => 'Bell offense '.ViolationType::count(), 'category' => 'Public Safety', 'fine_amount' => $fine]);
        $v = Violation::create(['violator_id' => $person->id, 'officer_id' => $this->account('enforcer')->id, 'violation_type_id' => $type->id, 'violation_date' => today(), 'confiscated_id' => 'None']);
        Citation::create(['violation_id' => $v->id, 'ticket_no' => Citation::generateTicketNo(), 'fine_amount' => $fine, 'due_date' => today()->addDays(15), 'payment_status' => 'pending']);
        return $v;
    }

    private function overdue(): Violation
    {
        $v = $this->record();
        $v->citation->update(['due_date' => today()->subDay()]);
        return $v;
    }

    public function test_bell_reports_nothing_pending_without_drawing_a_badge(): void
    {
        $this->record();
        $this->actingAs($this->account('admin'))->get(route('dashboard'))->assertOk()
            ->assertSee('Notifications: none')
            ->assertSee('No settlements need your attention.')
            ->assertDontSee('topbar-bell-count');
    }

    public function test_bell_announces_the_count_rather_than_an_unlabelled_dot(): void
    {
        $this->overdue();
        $this->overdue();
        $this->actingAs($this->account('admin'))->get(route('dashboard'))->assertOk()
            ->assertSee('Notifications: 2 settlements need your attention')
            ->assertSee('topbar-bell-count');
    }

    /** The bell is notification-only: it opens a panel, it never navigates by itself. */
    public function test_bell_is_a_panel_toggle_and_not_a_link(): void
    {
        $this->overdue();
        $html = $this->actingAs($this->account('admin'))->get(route('dashboard'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<button[^>]+id="notifBtn"/', $html);
        $this->assertStringContainsString('aria-controls="notifPanel"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        // No anchor may carry the bell class, or clicking it would navigate.
        $this->assertDoesNotMatchRegularExpression('/<a[^>]+topbar-bell/', $html);
    }

    public function test_panel_lists_the_individual_records_with_links_to_each(): void
    {
        $late = $this->overdue();
        $this->actingAs($this->account('admin'))->get(route('dashboard'))->assertOk()
            ->assertSee($late->personDetail('full_name'))
            ->assertSee('Record #'.$late->id.' · '.$late->violationType->offense_name)
            ->assertSee(route('violations.show', $late));
    }

    public function test_panel_separates_blocked_records_from_merely_overdue_ones(): void
    {
        $this->overdue();
        $blocked = $this->record(null);
        $blocked->citation->update(['fine_amount' => null]);

        $this->actingAs($this->account('admin'))->get(route('dashboard'))->assertOk()
            ->assertSee('Needs fine configured')
            ->assertSee('Fine not configured — verification is blocked', false)
            ->assertSee('Overdue verification')
            ->assertSee('Notifications: 2 settlements need your attention');
    }

    /** A record both past due and missing its fine is one notification, not two. */
    public function test_a_record_that_is_both_blocked_and_overdue_is_counted_once(): void
    {
        $v = $this->record(null);
        $v->citation->update(['fine_amount' => null, 'due_date' => today()->subDays(5)]);

        $this->actingAs($this->account('admin'))->get(route('dashboard'))->assertOk()
            ->assertSee('Notifications: 1 settlement needs your attention')
            ->assertSee('Needs fine configured')
            ->assertDontSee('Overdue verification');
    }

    public function test_group_links_target_the_matching_filtered_list(): void
    {
        $this->overdue();
        $this->actingAs($this->account('admin'))->get(route('dashboard'))->assertOk()
            ->assertSee(route('violations.index', ['payment_status' => 'overdue']));
    }

    public function test_verifying_the_settlement_clears_the_bell(): void
    {
        $v = $this->overdue();
        $this->actingAs($this->account('admin'))->get(route('dashboard'))->assertSee('Notifications: 1 settlement needs your attention');
        $v->citation->update(['payment_status' => 'paid']);
        $this->get(route('dashboard'))->assertOk()->assertSee('Notifications: none');
    }

    public function test_enforcers_do_not_receive_the_admin_bell(): void
    {
        $this->overdue();
        $this->actingAs($this->account('enforcer'))->get(route('enforcer.create'))->assertOk()
            ->assertDontSee('notifBtn');
    }
}
