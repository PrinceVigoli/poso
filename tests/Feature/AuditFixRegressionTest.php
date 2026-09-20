<?php
namespace Tests\Feature;

use App\Models\{User, Violator, Violation, ViolationType, Citation, PaymentEvent, TreasuryReceipt};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditFixRegressionTest extends TestCase
{
    use RefreshDatabase;
    private function account(string $role): User
    {
        $n = User::count();
        return User::create(['name' => $role, 'username' => $role.$n, 'email' => $role.$n.'@example.test', 'password' => 'password', 'role' => $role]);
    }
    private function record(?Violator $person = null, int $fine = 100): Violation
    {
        $person ??= Violator::create(['full_name' => 'Juan Dela Cruz', 'address' => 'Original address', 'vehicle_plate' => 'OLD-123']);
        $type = ViolationType::create(['offense_name' => 'Audit offense '.ViolationType::count(), 'category' => 'Public Safety', 'fine_amount' => $fine]);
        $v = Violation::create(['violator_id' => $person->id, 'officer_id' => $this->account('enforcer')->id, 'violation_type_id' => $type->id, 'violation_date' => today()->subDay()]);
        Citation::create(['violation_id' => $v->id, 'ticket_no' => Citation::generateTicketNo(), 'fine_amount' => $fine, 'due_date' => today()->addDays(10), 'payment_status' => 'pending']);
        return $v;
    }
    private function verify(Violation $v, array $extra = [])
    {
        return $this->post(route('violations.verify-payment', $v), array_merge(['treasury_receipt_no' => 'OR-123', 'receipt_date' => today()->toDateString(), 'receipt_total' => 300, 'receipt_verified' => 1], $extra));
    }
    public function test_same_name_requires_choice_and_can_create_a_different_person(): void
    {
        $old = $this->record();
        $this->actingAs($old->officer);
        $data = ['full_name' => 'Juan Dela Cruz', 'address' => 'Different household', 'vehicle_plate' => 'NEW-456', 'confiscated_id' => 'None', 'violation_type_id' => $old->violation_type_id];
        $this->post(route('enforcer.preview'), $data)->assertViewIs('violations.confirm_match')->assertSee('Original address');
        $this->assertNull(session('enforcer_preview'));
        $this->post(route('enforcer.preview'), $data + ['confirm_new' => 1])->assertRedirect(route('enforcer.review'));
        $this->post(route('enforcer.confirm'), ['preview_token' => session('enforcer_preview.token'), 'confirmed' => 1])->assertRedirect();
        $this->assertSame(2, Violator::count());
        $this->assertSame('Original address', $old->fresh()->personDetail('address'));
        $this->assertSame('Original address', $old->violator->fresh()->address);
        $this->assertNotSame($old->violator_id, Violation::latest('id')->first()->violator_id);
    }
    public function test_existing_person_keeps_history_and_blank_incident_plate_is_not_inherited(): void
    {
        $old = $this->record();
        $this->actingAs($old->officer);
        $this->post(route('enforcer.preview'), ['full_name' => 'Juan Dela Cruz', 'address' => 'New incident address', 'vehicle_plate' => '', 'confiscated_id' => 'None', 'violation_type_id' => $old->violation_type_id, 'matched_violator_id' => $old->violator_id])->assertRedirect(route('enforcer.review'));
        $this->assertNull(session('enforcer_preview.profile.vehicle_plate'));
        $this->post(route('enforcer.confirm'), ['preview_token' => session('enforcer_preview.token'), 'confirmed' => 1])->assertRedirect();
        $new = Violation::latest('id')->first();
        $this->assertSame(1, Violator::count());
        $this->assertNull($new->personDetail('vehicle_plate'));
        $this->assertSame('OLD-123', $old->fresh()->personDetail('vehicle_plate'));
        $old->violator->update(['address' => 'Later profile correction']);
        $this->assertSame('Original address', $old->fresh()->personDetail('address'));
        $this->actingAs($this->account('admin'))->get(route('reports.period', ['date' => $old->violation_date->toDateString()]))->assertOk()->assertSee('Original address')->assertDontSee('Later profile correction');
    }
    public function test_receipt_cannot_be_reused_for_an_unrelated_person_even_with_confirmation(): void
    {
        $one = $this->record();
        $two = $this->record();
        $this->actingAs($this->account('admin'));
        $this->verify($one)->assertSessionHasNoErrors();
        $this->verify($two, ['treasury_receipt_no' => ' or 123 ', 'reuse_receipt' => 1])->assertSessionHasErrors('treasury_receipt_no');
        $this->assertSame('pending', $two->citation->fresh()->payment_status);
        $this->assertSame(1, TreasuryReceipt::count());
        $this->assertSame(1, PaymentEvent::count());
    }
    public function test_shared_receipt_requires_confirmation_and_cannot_be_overallocated(): void
    {
        $one = $this->record();
        $two = $this->record($one->violator, 200);
        $three = $this->record($one->violator, 100);
        $this->actingAs($this->account('admin'));
        $this->verify($one)->assertSessionHasNoErrors();
        $this->verify($two)->assertSessionHasErrors('treasury_receipt_no');
        $this->verify($two, ['reuse_receipt' => 1])->assertSessionHasNoErrors();
        $this->verify($three, ['reuse_receipt' => 1])->assertSessionHasErrors('receipt_total');
        $this->verify($three, ['reuse_receipt' => 1, 'receipt_date' => today()->toDateString(), 'receipt_total' => 400])->assertSessionHasErrors('receipt_total');
        $this->assertSame(2, Citation::where('payment_status', 'paid')->count());
    }
    public function test_reversal_requires_reason_and_current_version_and_retains_history(): void
    {
        $v = $this->record();
        $admin = $this->account('admin');
        $this->actingAs($admin);
        $this->verify($v)->assertSessionHasNoErrors();
        $data = ['reason' => 'Wrong Treasury receipt was selected.', 'verification_version' => 1, 'reverse_confirmed' => 1];
        $this->post(route('violations.reverse-payment', $v), [])->assertSessionHasErrors(['reason', 'verification_version', 'reverse_confirmed']);
        $this->actingAs($v->officer)->post(route('violations.reverse-payment', $v), $data)->assertForbidden();
        $this->actingAs($admin)->post(route('violations.reverse-payment', $v), $data)->assertSessionHasNoErrors();
        $this->assertSame('pending', $v->fresh()->status);
        $this->assertNull($v->citation->fresh()->treasury_receipt_no);
        $this->assertSame('OR-123', PaymentEvent::where('action', 'reversed')->first()->before_state['treasury_receipt_no']);
        $this->verify($v, ['treasury_receipt_no' => 'OR-CORRECT'])->assertSessionHasNoErrors();
        $this->post(route('violations.reverse-payment', $v), $data)->assertSessionHasErrors('reason');
        $this->assertSame('OR-CORRECT', $v->citation->fresh()->treasury_receipt_no);
        $this->assertSame(3, PaymentEvent::count());
        $this->get(route('violations.show', $v))->assertOk()->assertSee('Wrong Treasury receipt was selected.')->assertSee('OR-123')->assertSee('OR-CORRECT');
        $this->get(route('reports.period'))->assertOk()->assertSee('Verification changes')->assertSee('Wrong Treasury receipt was selected.');
    }
    public function test_dismissed_records_do_not_become_overdue_or_count_as_unpaid(): void
    {
        $v = $this->record();
        $v->update(['status' => 'dismissed']);
        $v->citation->update(['due_date' => today()->subDay()]);
        $this->actingAs($this->account('admin'))->get(route('dashboard'))->assertOk()->assertViewHas('stats', fn ($s) => $s['pending_citations'] === 0 && $s['overdue_citations'] === 0 && $s['active_violators'] === 0);
        $this->assertSame('pending', $v->citation->fresh()->payment_status);
        $this->verify($v)->assertSessionHasErrors('receipt_verified');
        $this->get(route('reports.period', ['date' => $v->violation_date->toDateString()]))->assertOk()->assertSee('Dismissed');
        $this->get(route('violations.index', ['payment_status' => 'unpaid']))->assertViewHas('violations', fn ($rows) => $rows->isEmpty());
    }
    public function test_dashboard_includes_overdue_and_retired_offense_versions(): void
    {
        $v = $this->record();
        $v->citation->update(['due_date' => today()->subDay()]);
        $this->actingAs($this->account('admin'));
        $this->put(route('admin.violation-types.update', $v->violation_type_id), ['offense_name' => 'Amended public safety offense', 'category' => 'Public Safety', 'fine_amount' => 200])->assertSessionHasNoErrors();
        $this->get(route('dashboard'))->assertOk()->assertViewHas('stats', fn ($s) => $s['pending_citations'] === 1 && $s['overdue_citations'] === 1)->assertViewHas('topOffenses', fn ($types) => $types->sum('violations_count') === 1);
        $this->get(route('admin.violation-types'))->assertOk()->assertSee('data-category="Public Safety"', false);
        $this->post(route('admin.violation-types.store'), ['offense_name' => 'Public order offense', 'category' => 'Peace and Order', 'fine_amount' => 100])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('violation_types', ['offense_name' => 'Public order offense', 'category' => 'Peace and Order']);
    }
    public function test_internal_references_do_not_depend_on_daily_row_count(): void
    {
        $values = array_map(fn () => Citation::generateTicketNo(), range(1, 100));
        $this->assertSame(100, count(array_unique($values)));
    }
}