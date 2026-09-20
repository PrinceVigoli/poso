<?php

namespace Tests\Feature;

use App\Models\Citation;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationType;
use App\Models\Violator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViolatorArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_archives_profile_and_new_violation_restores_it_with_history(): void
    {
        $user = User::create(['name' => 'Officer', 'username' => 'officer', 'email' => 'officer@example.test', 'password' => 'password', 'role' => 'admin']);
        $this->actingAs($user);
        $person = Violator::create(['full_name' => 'Archive Test Person']);
        $type = ViolationType::create(['offense_name' => 'Test offense', 'fine_amount' => 100]);
        $violation = Violation::create(['violator_id' => $person->id, 'officer_id' => $user->id, 'violation_type_id' => $type->id, 'violation_date' => today(), 'location' => 'Test street', 'status' => 'pending']);
        $citation = Citation::create(['violation_id' => $violation->id, 'ticket_no' => 'TEST-001', 'fine_amount' => 100, 'due_date' => today(), 'payment_status' => 'pending']);

        $this->get(route('violators.index'))->assertOk()->assertSee($person->full_name);
        $this->post(route('citations.pay', $citation), ['treasury_receipt_no' => 'OR-001', 'receipt_date' => today()->toDateString(), 'receipt_total' => 500, 'receipt_verified' => 1])->assertRedirect();
        $this->assertTrue($person->isArchived());
        $this->get(route('violators.index'))->assertOk()->assertDontSee($person->full_name);
        $this->get(route('violators.index', ['status' => 'archived', 'search' => 'Archive Test']))->assertOk()->assertSee($person->full_name);
        $this->get(route('violators.show', $person))->assertOk()->assertSee('Test offense')->assertSee('Archived (settled)');
        $enforcer = User::create(['name' => 'Enforcer', 'username' => 'enforcer', 'email' => 'enforcer@example.test', 'password' => 'password', 'role' => 'enforcer']);
        $this->actingAs($enforcer)->get(route('violations.create'))->assertOk();
        $this->post(route('enforcer.preview'), ['full_name' => $person->full_name, 'address' => 'Luna', 'confiscated_id' => 'None', 'violation_type_id' => $type->id, 'matched_violator_id' => $person->id, 'confirm_duplicate' => 1])->assertRedirect(route('enforcer.review'));
        $this->post(route('enforcer.confirm'), ['preview_token' => session('enforcer_preview.token'), 'confirmed' => 1])->assertRedirect();
        $this->actingAs($user);
        $this->assertFalse($person->isArchived());
        $this->assertSame(2, $person->violations()->count());
        $this->assertSame(1, Violator::count());
        $this->get(route('violators.index'))->assertSee($person->full_name);
        $this->get(route('violators.index', ['status' => 'archived']))->assertDontSee($person->full_name);
        $this->get(route('violators.show', $person))->assertSee('Test offense')->assertSee('Record #2');
    }

    public function test_existing_paid_records_archive_but_missing_or_unpaid_citations_keep_profile_active(): void
    {
        $user = User::create(['name' => 'Officer', 'username' => 'officer', 'email' => 'officer@example.test', 'password' => 'password', 'role' => 'admin']);
        $person = Violator::create(['full_name' => 'Existing Person']);
        $this->assertFalse(Violator::active()->whereKey($person->id)->exists());
        $type = ViolationType::create(['offense_name' => 'Test offense', 'fine_amount' => 100]);
        $data = ['violator_id' => $person->id, 'officer_id' => $user->id, 'violation_type_id' => $type->id, 'violation_date' => today(), 'location' => 'Test street', 'status' => 'settled'];
        $first = Violation::create($data);
        Citation::create(['violation_id' => $first->id, 'ticket_no' => 'OLD-001', 'fine_amount' => 100, 'due_date' => today(), 'payment_status' => 'paid']);
        $this->assertTrue(Violator::archived()->whereKey($person->id)->exists());
        $second = Violation::create(array_merge($data, ['status' => 'pending']));
        $this->assertFalse($person->isArchived());
        $this->assertTrue(Violator::active()->whereKey($person->id)->exists());
        $citation = Citation::create(['violation_id' => $second->id, 'ticket_no' => 'OLD-002', 'fine_amount' => 100, 'due_date' => today(), 'payment_status' => 'overdue']);
        $this->assertFalse($person->isArchived());
        $citation->update(['payment_status' => 'paid']);
        $this->assertTrue($person->isArchived());
    }

    public function test_profiles_without_violations_are_hidden_from_active_and_archived_but_retained_in_all(): void
    {
        $user = User::create(['name' => 'Officer', 'username' => 'officer', 'email' => 'officer@example.test', 'password' => 'password', 'role' => 'admin']);
        $this->actingAs($user);
        $person = Violator::create(['full_name' => 'Profile Without Violations']);

        $this->get(route('violators.index'))->assertOk()->assertDontSee($person->full_name);
        $this->get(route('violators.index', ['status' => 'active', 'search' => $person->full_name]))->assertOk()->assertViewHas('violators', fn ($violators) => $violators->total() === 0);
        $this->get(route('violators.index', ['status' => 'archived']))->assertOk()->assertDontSee($person->full_name);
        $this->get(route('violators.index', ['status' => 'all']))->assertOk()->assertSee($person->full_name);
        $this->get(route('violations.create'))->assertForbidden();
        $this->get(route('dashboard'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['active_violators'] === 0);
    }
}
