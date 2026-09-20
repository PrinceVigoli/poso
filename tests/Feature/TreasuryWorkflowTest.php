<?php

namespace Tests\Feature;

use App\Models\{Citation, User, Violation, ViolationType, Violator};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreasuryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role): User
    {
        return User::create(['name' => $role, 'username' => $role, 'email' => "$role@example.test", 'password' => 'password', 'role' => $role]);
    }

    private function record(User $user, string $date = '2026-09-01'): Citation
    {
        $person = Violator::create(['full_name' => 'Treasury Test Person', 'address' => 'Luna, Apayao']);
        $type = ViolationType::where('offense_name', 'No Helmet')->firstOrFail();
        $v = Violation::create(['violator_id' => $person->id, 'officer_id' => $user->id, 'violation_type_id' => $type->id, 'violation_date' => $date, 'location' => 'Luna', 'status' => 'pending']);
        return Citation::create(['violation_id' => $v->id, 'ticket_no' => 'TEST-'.Citation::count(), 'fine_amount' => 500, 'due_date' => '2026-10-01', 'payment_status' => 'pending']);
    }

    public function test_only_admin_can_verify_receipts_and_settlement_uses_verification_day(): void
    {
        // 16:30 UTC is already the next calendar day in Luna, Apayao.
        $this->travelTo(\Carbon\Carbon::parse('2026-09-09 16:30:00', 'UTC'));
        $officer = $this->account('enforcer');
        $admin = $this->account('admin');
        $citation = $this->record($officer);
        $this->actingAs($officer)->post(route('violations.verify-payment', $citation->violation), ['treasury_receipt_no' => 'OR-123', 'receipt_date' => today()->toDateString(), 'receipt_total' => 500, 'receipt_verified' => 1])->assertForbidden();
        $this->actingAs($admin)->post(route('violations.verify-payment', $citation->violation))->assertSessionHasErrors(['treasury_receipt_no', 'receipt_verified']);
        $this->assertSame('pending', $citation->fresh()->payment_status);
        $this->post(route('violations.verify-payment', $citation->violation), ['treasury_receipt_no' => 'OR-123', 'receipt_date' => today()->toDateString(), 'receipt_total' => 500, 'receipt_verified' => 1, 'paid_at' => '2026-09-02'])->assertRedirect();
        $citation->refresh();
        $this->assertSame('2026-09-10', $citation->paid_at->toDateString());
        $this->assertSame($admin->id, $citation->verified_by);
        $this->assertSame('OR-123', $citation->treasury_receipt_no);
        $this->assertSame('settled', $citation->violation->status);
        $this->assertTrue($citation->violation->violator->isArchived());
        $this->travelTo(\Carbon\Carbon::parse('2026-09-10 16:30:00', 'UTC'));
        $this->post(route('violations.verify-payment', $citation->violation), ['treasury_receipt_no' => 'OR-OTHER', 'receipt_date' => today()->toDateString(), 'receipt_total' => 500, 'receipt_verified' => 1])->assertRedirect();
        $this->assertSame('OR-123', $citation->fresh()->treasury_receipt_no);
        $this->assertSame('2026-09-10', $citation->fresh()->paid_at->toDateString());
        $this->get(route('dashboard'))->assertDontSee('Total collected');
        $this->travelBack();
    }

    public function test_editing_status_cannot_bypass_verification_or_mark_dismissed_as_paid(): void
    {
        $officer = $this->account('enforcer');
        $this->actingAs($officer);
        $citation = $this->record($officer);
        $v = $citation->violation;
        $data = ['violator_id' => $v->violator_id, 'violation_type_id' => $v->violation_type_id, 'violation_date' => '2026-09-01', 'location' => 'Luna', 'status' => 'settled'];
        $this->put(route('violations.update', $v), $data)->assertForbidden();
        $this->assertSame('pending', $citation->fresh()->payment_status);
        $this->put(route('violations.update', $v), array_merge($data, ['status' => 'dismissed']))->assertForbidden();
        $this->assertSame('pending', $citation->fresh()->payment_status);
        $this->assertNull($citation->fresh()->paid_at);
    }

    public function test_catalog_starts_with_three_offenses_and_amendment_preserves_history(): void
    {
        $this->assertSame(['No Helmet', 'No Side Mirror', 'No Vest'], ViolationType::orderBy('offense_name')->pluck('offense_name')->all());
        $admin = $this->account('admin');
        $this->actingAs($admin);
        $citation = $this->record($admin);
        $old = $citation->violation->violationType;
        $this->put(route('admin.violation-types.update', $old), ['offense_name' => 'Helmet ordinance amended', 'category' => 'Traffic Violation', 'fine_amount' => 750])->assertRedirect();
        $this->assertTrue($old->fresh()->trashed());
        $this->assertSame('No Helmet', $citation->violation->fresh()->violationType->offense_name);
        $this->assertEquals(500, $citation->fresh()->fine_amount);
        $new = ViolationType::where('offense_name', 'Helmet ordinance amended')->firstOrFail();
        $this->assertEquals(750, $new->fine_amount);
        $this->delete(route('admin.violation-types.destroy', $new))->assertRedirect();
        $this->actingAs($this->account('enforcer'));
        $this->get(route('violations.create'))->assertOk()->assertDontSee('Helmet ordinance amended');
        $this->post(route('violations.store'), ['violator_id' => $citation->violation->violator_id, 'violation_type_id' => $old->id, 'violation_date' => today()->toDateString(), 'location' => 'Luna'])->assertSessionHasErrors('violation_type_id');
        $this->actingAs($admin);
        $this->post(route('admin.violation-types.store'), ['offense_name' => 'New ordinance', 'category' => 'Traffic Violation', 'fine_amount' => null])->assertRedirect();
        $this->assertNull(ViolationType::where('offense_name', 'New ordinance')->firstOrFail()->fine_amount);
    }

    public function test_admin_monitors_records_but_cannot_enter_edit_or_delete_them(): void
    {
        $enforcer = $this->account('enforcer');
        $citation = $this->record($enforcer);
        $violation = $citation->violation;
        $person = $violation->violator;
        $this->actingAs($this->account('admin'));
        foreach (['dashboard', 'violations.index', 'violators.index'] as $route) {
            $this->get(route($route))->assertOk()->assertDontSee('Record Violation')->assertDontSee('Add Violator')->assertDontSee('> Citations', false)->assertDontSee($citation->ticket_no);
        }
        $this->get(route('violations.show', $violation))->assertOk()->assertDontSee('Edit Violation');
        $this->get(route('violators.show', $person))->assertOk()->assertDontSee('Add Violation')->assertDontSee($citation->ticket_no);
        $this->get(route('violations.create'))->assertForbidden();
        $this->post(route('violations.store'), [])->assertForbidden();
        $this->get(route('violators.create'))->assertForbidden();
        $this->post(route('violators.store'), [])->assertForbidden();
        foreach (['violations' => $violation, 'violators' => $person] as $resource => $record) {
            $this->get(route($resource.'.edit', $record))->assertForbidden();
            $this->put(route($resource.'.update', $record), [])->assertForbidden();
            $this->delete(route($resource.'.destroy', $record))->assertForbidden();
        }
        $this->assertDatabaseHas('violations', ['id' => $violation->id]);
        $this->assertDatabaseHas('citations', ['id' => $citation->id, 'payment_status' => 'pending']);
        $this->actingAs($enforcer)->get(route('violations.create'))->assertOk();
        $this->post(route('violations.verify-payment', $citation->violation), ['treasury_receipt_no' => 'OR-123', 'receipt_date' => today()->toDateString(), 'receipt_total' => 500, 'receipt_verified' => 1])->assertForbidden();
    }

    public function test_reports_are_admin_only_and_use_calendar_periods_and_settlement_dates(): void
    {
        $officer = $this->account('enforcer');
        $admin = $this->account('admin');
        $this->actingAs($officer)->get(route('reports.period'))->assertForbidden();
        $citation = $this->record($officer, '2026-08-31');
        $citation->update(['payment_status' => 'paid', 'paid_at' => '2026-09-10']);
        $this->actingAs($admin)->get(route('reports.period', ['period' => 'daily', 'date' => '2026-09-10']))->assertOk()
            ->assertViewHas('records', fn ($records) => $records->isEmpty())->assertViewHas('settlements', fn ($records) => $records->count() === 1);
        $this->get(route('reports.period', ['period' => 'weekly', 'date' => '2026-09-10']))->assertOk()->assertViewHas('dateFrom', '2026-09-07')->assertViewHas('dateTo', '2026-09-13');
        $this->get(route('reports.period', ['period' => 'monthly', 'date' => '2026-09-10']))->assertOk()->assertViewHas('dateFrom', '2026-09-01')->assertViewHas('dateTo', '2026-09-30');
        $this->get(route('reports.period', ['period' => 'yearly']))->assertSessionHasErrors('period');
        $this->get(route('reports.period', ['date' => 'invalid']))->assertSessionHasErrors('date');
    }
}
