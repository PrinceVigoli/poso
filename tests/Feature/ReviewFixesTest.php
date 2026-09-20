<?php

namespace Tests\Feature;

use App\Models\{Citation, User, Violation, ViolationType, Violator};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role): User
    {
        $n = User::count();
        return User::create(['name' => 'Review '.$role.' '.$n, 'username' => $role.$n, 'email' => $role.$n.'@example.test', 'password' => 'original-test-password', 'role' => $role]);
    }

    /** @param string[] $statuses one violation per status */
    private function person(string $name, array $statuses): Violator
    {
        $person = Violator::create(['full_name' => $name, 'address' => 'Incident address']);
        $officer = $this->account('enforcer');
        $type = ViolationType::firstOrCreate(['offense_name' => 'Review offense'], ['category' => 'Public Safety', 'fine_amount' => 500]);
        foreach ($statuses as $status) {
            $v = Violation::create(['violator_id' => $person->id, 'officer_id' => $officer->id, 'violation_type_id' => $type->id, 'violation_date' => today(), 'status' => $status, 'confiscated_id' => 'None']);
            Citation::create(['violation_id' => $v->id, 'ticket_no' => Citation::generateTicketNo(), 'fine_amount' => 500, 'due_date' => today()->addDays(15), 'payment_status' => 'pending']);
        }
        return $person;
    }

    // ── Repeat offenders ────────────────────────────────────────────────

    /** The list counted dismissed violations; the profile did not. Same person, two answers. */
    public function test_repeat_offender_flag_agrees_between_list_and_profile(): void
    {
        $person = $this->person('Borderline Person', ['pending', 'pending', 'dismissed']);
        $this->actingAs($this->account('admin'));

        $list = $this->get(route('violators.index', ['status' => 'all']))->assertOk()->getContent();
        $profile = $this->get(route('violators.show', $person))->assertOk()->getContent();

        $this->assertFalse($person->isRepeatOffender(), 'Two counted violations must not reach a threshold of three.');
        $this->assertStringNotContainsString('badge-repeat', $list, 'The list flagged a person the rule does not.');
        $this->assertStringNotContainsString('Repeat Offender', $profile);
    }

    public function test_reaching_the_threshold_flags_the_person_on_both_screens(): void
    {
        $person = $this->person('Repeat Person', ['pending', 'pending', 'pending', 'dismissed']);
        $this->actingAs($this->account('admin'));

        $this->assertTrue($person->isRepeatOffender());
        $this->assertStringContainsString('badge-repeat', $this->get(route('violators.index', ['status' => 'all']))->getContent());
        $this->get(route('violators.show', $person))->assertOk()->assertSee('Repeat Offender');
    }

    public function test_threshold_is_configurable_in_one_place(): void
    {
        $person = $this->person('Two Strike Person', ['pending', 'pending']);
        $this->assertFalse($person->isRepeatOffender());

        config(['portals.repeat_offender_threshold' => 2]);
        $this->assertTrue($person->fresh()->isRepeatOffender());

        $this->actingAs($this->account('admin'))->get(route('dashboard'))
            ->assertViewHas('stats', fn ($stats) => $stats['repeat_offenders'] === 1);
    }

    /** The badge printed every row, including dismissed ones that never triggered it. */
    public function test_profile_badge_reports_the_count_that_triggered_it(): void
    {
        $person = $this->person('Mixed Person', ['pending', 'pending', 'pending', 'dismissed', 'dismissed']);
        $this->actingAs($this->account('admin'))->get(route('violators.show', $person))->assertOk()
            ->assertSee('Repeat Offender (3 counted violations)')
            ->assertSee('5 violations on record');
    }

    // ── Treasury verification ───────────────────────────────────────────

    private function settled(): Citation
    {
        $person = $this->person('Settled Person', ['pending']);
        $citation = $person->violations()->first()->citation;
        $this->actingAs($this->account('admin'))->post(route('citations.pay', $citation), [
            'treasury_receipt_no' => 'OR-100', 'receipt_date' => today()->toDateString(),
            'receipt_total' => 500, 'receipt_verified' => 1,
        ])->assertRedirect();
        return $citation->fresh();
    }

    /** It answered "Treasury payment verified" while recording nothing at all. */
    public function test_verifying_a_settled_record_with_a_different_receipt_is_rejected(): void
    {
        $citation = $this->settled();
        $this->assertSame('OR-100', $citation->treasury_receipt_no);

        $this->post(route('citations.pay', $citation), [
            'treasury_receipt_no' => 'OR-999', 'receipt_date' => today()->toDateString(),
            'receipt_total' => 500, 'receipt_verified' => 1,
        ])->assertSessionHasErrors('treasury_receipt_no');

        $this->assertSame('OR-100', $citation->fresh()->treasury_receipt_no, 'The stored receipt must not change.');
    }

    public function test_resubmitting_the_same_receipt_remains_idempotent(): void
    {
        $citation = $this->settled();
        $version = $citation->verification_version;

        $this->post(route('citations.pay', $citation), [
            'treasury_receipt_no' => 'or 100', 'receipt_date' => today()->toDateString(),
            'receipt_total' => 500, 'receipt_verified' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($version, $citation->fresh()->verification_version, 'A double submit must not re-verify.');
    }

    // ── Dashboard queues ────────────────────────────────────────────────

    /**
     * needs_review used to be a subset of the "awaiting verification" count,
     * so the two dashboard buttons double-counted the same records. They must
     * partition the unverified set instead.
     */
    public function test_the_two_dashboard_queues_do_not_count_the_same_record_twice(): void
    {
        $this->person('Ready Person', ['pending', 'pending']);
        $blocked = $this->person('Blocked Person', ['pending']);
        $blocked->violations()->first()->citation->update(['fine_amount' => null]);

        $this->actingAs($this->account('admin'))->get(route('dashboard'))->assertOk()
            ->assertViewHas('stats', function ($stats) {
                return $stats['ready_to_verify'] === 2
                    && $stats['needs_review'] === 1
                    // The parts must add up to the whole.
                    && $stats['ready_to_verify'] + $stats['needs_review'] === $stats['pending_citations'];
            });
    }

    public function test_each_queue_filter_returns_only_its_own_records(): void
    {
        $this->person('Ready Person', ['pending']);
        $blocked = $this->person('Blocked Person', ['pending']);
        $blocked->violations()->first()->citation->update(['fine_amount' => null]);
        $this->actingAs($this->account('admin'));

        // Scoped to the list: the topbar notification panel legitimately names
        // blocked records on every admin page.
        $named = fn (string $name) => fn ($rows) => $rows->pluck('person_snapshot.full_name')->all() === [$name];

        $this->get(route('violations.index', ['payment_status' => 'ready']))
            ->assertOk()->assertViewHas('violations', $named('Ready Person'));

        $this->get(route('violations.index', ['payment_status' => 'needs_review']))
            ->assertOk()->assertViewHas('violations', $named('Blocked Person'));
    }

    // ── Reports ─────────────────────────────────────────────────────────

    /** The old index page only linked to a screen that selects its own period. */
    public function test_the_reports_link_goes_straight_to_the_report(): void
    {
        $this->actingAs($this->account('admin'))
            ->get(route('reports.index'))->assertRedirect(route('reports.period'));
    }

    public function test_the_report_screen_selects_its_own_period(): void
    {
        $this->actingAs($this->account('admin'));

        foreach (['daily', 'weekly', 'monthly'] as $period) {
            $this->get(route('reports.period', ['period' => $period]))->assertOk()
                ->assertViewHas('period', $period)
                ->assertSee('Report period');
        }
    }

    public function test_the_report_screen_defaults_to_daily(): void
    {
        $this->actingAs($this->account('admin'))->get(route('reports.period'))->assertOk()
            ->assertViewHas('period', 'daily');
    }

    // ── Login ───────────────────────────────────────────────────────────

    /** A junk password used to reveal which usernames existed. */
    public function test_login_does_not_reveal_that_a_deactivated_username_exists(): void
    {
        $user = $this->account('enforcer');
        $user->update(['is_active' => false]);

        $this->post(route('login.submit'), ['username' => $user->username, 'password' => 'not-the-password'])
            ->assertSessionHasErrors(['username' => 'Invalid username or password.']);

        $this->post(route('login.submit'), ['username' => 'no-such-account', 'password' => 'not-the-password'])
            ->assertSessionHasErrors(['username' => 'Invalid username or password.']);
    }

    /** With the correct password, the real reason is still reported. */
    public function test_a_deactivated_account_is_told_so_once_the_password_is_right(): void
    {
        $user = $this->account('enforcer');
        $user->update(['is_active' => false]);

        $this->post(route('login.submit'), ['username' => $user->username, 'password' => 'original-test-password'])
            ->assertSessionHasErrors(['username' => 'Your account has been deactivated. Contact admin.']);
        $this->assertGuest();
    }

    public function test_an_active_account_still_signs_in(): void
    {
        $user = $this->account('admin');
        $this->post(route('login.submit'), ['username' => $user->username, 'password' => 'original-test-password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }
}
