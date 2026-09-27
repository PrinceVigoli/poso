<?php

namespace Tests\Feature;

use App\Models\{User, Violator, Violation, ViolationType, Citation, PaymentEvent};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Auth, DB};
use Tests\TestCase;

class ArchitectureRemediationTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role): User
    {
        $n = User::count();
        return User::create(['name' => 'Audit '.$role.' '.$n, 'username' => $role.$n, 'email' => $role.$n.'@example.test', 'password' => 'original-test-password', 'role' => $role]);
    }

    private function record(?int $fine = 500, ?Violator $person = null, ?ViolationType $type = null): Violation
    {
        $person ??= Violator::create(['full_name' => 'Test Citizen '.(Violator::count()+1), 'address' => 'Incident address']);
        $type ??= ViolationType::create(['offense_name' => 'Public safety offense '.ViolationType::count(), 'category' => 'Public Safety', 'fine_amount' => $fine]);
        $v = Violation::create(['violator_id' => $person->id, 'officer_id' => $this->account('enforcer')->id, 'violation_type_id' => $type->id, 'violation_date' => today(), 'confiscated_id' => 'None']);
        Citation::create(['violation_id' => $v->id, 'ticket_no' => Citation::generateTicketNo(), 'fine_amount' => $fine, 'due_date' => today()->addDays(15), 'payment_status' => 'pending']);
        return $v;
    }

    private function receipt(array $extra = []): array
    {
        return array_merge(['treasury_receipt_no' => 'OR-NEW-001', 'receipt_date' => today()->subDay()->toDateString(), 'receipt_total' => 1000, 'receipt_verified' => 1], $extra);
    }

    public function test_reset_revokes_old_session_and_new_password_can_sign_in(): void
    {
        $enforcer = $this->account('enforcer');
        $this->post(route('login.submit'), ['username' => $enforcer->username, 'password' => 'original-test-password', 'remember' => 1])->assertRedirect();
        $oldSession = session()->all();
        $oldRemember = $enforcer->fresh()->remember_token;
        $this->actingAs($this->account('admin'))->put(route('admin.users.update', $enforcer), [
            'name' => $enforcer->name, 'username' => $enforcer->username, 'email' => $enforcer->email,
            'role' => 'enforcer', 'is_active' => 1, 'password' => 'new-long-test-password', 'password_confirmation' => 'new-long-test-password',
        ])->assertSessionHasNoErrors();
        $this->assertNotNull($oldRemember);
        $this->assertNull($enforcer->fresh()->remember_token);
        Auth::forgetGuards();
        $this->withSession($oldSession)->get(route('enforcer.create'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post(route('login.submit'), ['username' => $enforcer->username, 'password' => 'original-test-password'])->assertSessionHasErrors('username');
        $this->post(route('login.submit'), ['username' => $enforcer->username, 'password' => 'new-long-test-password'])->assertRedirect();
        $this->get(route('enforcer.create'))->assertOk();
    }

    public function test_unknown_fine_is_reconciled_once_by_admin_with_audited_evidence(): void
    {
        $v = $this->record(null);
        $data = ['fine_amount' => 500, 'ordinance_reference' => 'Ordinance 2026-001 section 2', 'reason' => 'Confirmed the amount applicable on the incident date.', 'verification_version' => 0, 'fine_confirmed' => 1];
        $this->actingAs($v->officer)->post(route('violations.reconcile-fine', $v), $data)->assertForbidden();
        $this->actingAs($this->account('admin'))->get(route('violations.index', ['payment_status' => 'needs_review']))->assertViewHas('violations', fn ($rows) => $rows->count() === 1);
        $this->post(route('violations.reconcile-fine', $v), [])->assertSessionHasErrors(['fine_amount', 'ordinance_reference', 'reason', 'fine_confirmed']);
        $this->post(route('violations.reconcile-fine', $v), $data)->assertSessionHasNoErrors();
        $this->assertEquals(500, $v->citation->fresh()->fine_amount);
        $this->assertNull($v->violationType->fresh()->fine_amount);
        $event = PaymentEvent::where('action', 'fine_configured')->firstOrFail();
        $this->assertNull($event->before_state['fine_amount']);
        $this->assertStringContainsString('Ordinance 2026-001', $event->reason);
        $this->post(route('violations.reconcile-fine', $v), $data)->assertSessionHasErrors('fine_amount');
        $this->post(route('violations.verify-payment', $v), $this->receipt())->assertSessionHasNoErrors();
        $this->assertSame('Settled', $v->fresh()->payment_label);
    }

    public function test_receipt_date_is_distinct_validated_consistent_and_retained_after_reversal(): void
    {
        $one = $this->record(); $two = $this->record(500, $one->violator);
        $this->actingAs($this->account('admin'));
        $this->post(route('violations.verify-payment', $one), $this->receipt(['receipt_date' => today()->addDay()->toDateString()]))->assertSessionHasErrors('receipt_date');
        $this->post(route('violations.verify-payment', $one), $this->receipt(['receipt_date' => null]))->assertSessionHasErrors('receipt_date');
        $this->post(route('violations.verify-payment', $one), $this->receipt())->assertSessionHasNoErrors();
        $this->assertSame(today()->subDay()->toDateString(), $one->citation->fresh()->receipt_date->toDateString());
        $this->assertSame(today()->toDateString(), $one->citation->fresh()->paid_at->toDateString());
        $this->postJson(route('receipts.review'), ['receipt_no' => 'OR-NEW-001', 'violation_id' => $two->id])->assertOk()->assertJsonPath('allocated', 500)->assertJsonPath('remaining', 500)->assertJsonPath('same_person', true)->assertJsonPath('allocations.0.reference', $one->id);
        $this->post(route('violations.verify-payment', $two), $this->receipt(['reuse_receipt' => 1, 'receipt_date' => today()->toDateString()]))->assertSessionHasErrors('receipt_date');
        $this->post(route('violations.verify-payment', $two), $this->receipt(['reuse_receipt' => 1]))->assertSessionHasNoErrors();
        $this->post(route('violations.reverse-payment', $one), ['reason' => 'Verified against an incorrect official receipt.', 'verification_version' => 1, 'reverse_confirmed' => 1])->assertSessionHasNoErrors();
        $this->assertNull($one->citation->fresh()->receipt_date);
        $this->assertNotNull(PaymentEvent::where('action', 'reversed')->firstOrFail()->before_state['receipt_date']);
        $this->actingAs($one->officer)->postJson(route('receipts.review'), ['receipt_no'=>'OR-NEW-001', 'violation_id'=>$one->id])->assertForbidden();
    }

    public function test_database_rejects_a_second_payment_for_one_violation(): void
    {
        $v = $this->record();
        $this->expectException(\Illuminate\Database\QueryException::class);
        Citation::create(['violation_id'=>$v->id, 'ticket_no'=>Citation::generateTicketNo(), 'fine_amount'=>100, 'due_date'=>today(), 'payment_status'=>'paid']);
    }

    public function test_drafts_survive_navigation_and_two_previews_can_be_confirmed(): void
    {
        $v = $this->record();
        $this->actingAs($v->officer);
        $input = ['location'=>'Poblacion checkpoint','top_number'=>'TOP-001','full_name'=>'Different Citizen Alpha','address'=>'Example address','confiscated_id'=>'None','violation_type_id'=>$v->violation_type_id,'confirm_new'=>1];
        $this->post(route('enforcer.preview'), $input)->assertRedirect(); $one = session('enforcer_preview.token');
        $this->get(route('enforcer.create'))->assertOk();
        $this->get(route('enforcer.review', ['draft'=>$one]))->assertOk()->assertSee('Different Citizen Alpha');
        $this->post(route('enforcer.preview'), array_merge($input, ['top_number'=>'TOP-002','full_name'=>'Separate Person Beta']))->assertRedirect(); $two = session('enforcer_preview.token');
        $this->post(route('enforcer.confirm'), ['preview_token'=>$one,'confirmed'=>1])->assertSessionHasNoErrors();
        $this->assertSame($two, session('enforcer_preview.token'));
        $this->post(route('enforcer.confirm'), ['preview_token'=>$two,'confirmed'=>1])->assertSessionHasNoErrors();
        $this->assertSame(3, Violation::count());
        $input['top_number'] = 'TOP-003';
        $this->post(route('enforcer.preview'), $input)->assertRedirect();
        $this->post(route('enforcer.clear'))->assertRedirect()->assertSessionMissing('enforcer_drafts');
    }

    public function test_my_submissions_only_lists_the_issuing_enforcers_records(): void
    {
        $own = $this->record(); $other = $this->record();
        $this->actingAs($own->officer)->get(route('enforcer.index'))->assertOk()->assertSee($own->personDetail('full_name'))->assertDontSee($other->personDetail('full_name'));
        $this->get(route('enforcer.show', $other))->assertForbidden();
    }

    public function test_overdue_is_consistent_without_read_requests_writing_payment_rows(): void
    {
        $v = $this->record(); $v->citation->update(['due_date'=>today()->subDay()]);
        $dismissed = $this->record(); $dismissed->update(['status'=>'dismissed']); $dismissed->citation->update(['due_date'=>today()->subDays(2), 'payment_status'=>'overdue']);
        $writes=[]; DB::listen(function ($q) use (&$writes) { if (preg_match('/^\s*(update|insert|delete)/i', $q->sql)) $writes[]=$q->sql; });
        $this->actingAs($this->account('admin'));
        $writes=[];
        $this->get(route('violations.index',['payment_status'=>'overdue']))->assertViewHas('violations',fn($rows)=>$rows->count()===1);
        $this->get(route('dashboard'))->assertViewHas('stats',fn($stats)=>$stats['overdue_citations']===1);
        $this->assertSame('pending',$v->citation->fresh()->payment_status);
        $this->assertSame('overdue',$v->citation->fresh()->effective_payment_status);
        $this->assertSame([], $writes);
    }

    public function test_offense_versions_share_filter_and_ranking_but_preserve_original_details(): void
    {
        $old=$this->record(); $this->actingAs($this->account('admin'));
        $this->put(route('admin.violation-types.update',$old->violation_type_id),['offense_name'=>'Amended offense','category'=>'Public Safety','fine_amount'=>750])->assertSessionHasNoErrors();
        $type=ViolationType::where('offense_name','Amended offense')->firstOrFail();
        $new=$this->record(750,null,$type);
        $this->get(route('violations.index',['type'=>$type->id]))->assertViewHas('violations',fn($rows)=>$rows->count()===2);
        $this->get(route('dashboard'))->assertViewHas('topOffenses',fn($rows)=>$rows->count()===1 && (int)$rows->first()->violations_count===2);
        $this->assertNotSame($type->offense_name,$old->fresh()->violationType->offense_name);
        $type->delete();
        $this->get(route('violations.index'))->assertViewHas('violationTypes',fn($types)=>$types->contains('offense_key',$type->offense_key));
    }

    public function test_reports_paginate_and_full_export_escapes_spreadsheet_formulas(): void
    {
        $v=$this->record(); $profile=$v->person_snapshot; $profile['full_name']='=HYPERLINK("example")'; $v->update(['person_snapshot'=>$profile]);
        for($i=0;$i<51;$i++) Violation::create(['violator_id'=>$v->violator_id,'officer_id'=>$v->officer_id,'violation_type_id'=>$v->violation_type_id,'violation_date'=>today()]);
        $this->actingAs($this->account('admin'))->get(route('reports.period'))->assertViewHas('records',fn($rows)=>$rows->total()===52 && $rows->count()===50);
        $response=$this->get(route('reports.period',['download'=>'csv']))->assertOk();
        $csv=$response->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK",$csv);
        $this->assertSame(52,substr_count($csv,'Apprehension,'));
        $this->actingAs($v->officer)->get(route('reports.period',['download'=>'csv']))->assertForbidden();
    }

    public function test_incident_address_and_local_assets_are_rendered(): void
    {
        $v=$this->record(); $v->violator->update(['address'=>'Later profile address']);
        $response=$this->actingAs($this->account('admin'))->get(route('violations.show',$v))->assertOk()->assertSee('Incident address')->assertSee('Required fine')->assertSee('receipt_date',false);
        $response->assertDontSee('cdn.jsdelivr.net')->assertDontSee('fonts.googleapis.com')->assertDontSee('Later profile address');
        $this->get(route('search'))->assertSee('Check Settlement Status')->assertSee('Results stay open for 15 minutes');
    }
}
