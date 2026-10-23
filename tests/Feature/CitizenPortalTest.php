<?php
namespace Tests\Feature;

use App\Models\{AuditLog, Citation, User, Violation, ViolationType, Violator};
use App\Support\CitizenAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CitizenPortalTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role = 'enforcer'): User
    {
        $n = User::count();
        return User::create(['name' => 'Staff '.$n, 'username' => 'staff'.$n, 'email' => 'staff'.$n.'@example.test', 'password' => 'password', 'role' => $role]);
    }

    private function record(string $status = 'pending'): array
    {
        $owner = $this->account();
        $person = Violator::firstOrCreate(['full_name' => 'Citizen Test'], ['address' => 'Private home address', 'vehicle_plate' => 'ABC-123', 'license_no' => 'N01-123']);
        $type = ViolationType::firstOrCreate(['offense_name' => 'Private offense'], ['fine_amount' => 500]);
        $record = Violation::create(['violator_id' => $person->id, 'officer_id' => $owner->id, 'violation_type_id' => $type->id, 'violation_date' => today()]);
        Citation::create(['violation_id' => $record->id, 'ticket_no' => 'TOP-'.$record->id, 'fine_amount' => 500, 'due_date' => today(), 'payment_status' => $status, 'paid_at' => $status === 'paid' ? today() : null]);
        $code = CitizenAccess::issue($record, 'Test ticket issuance.');
        return [$record->fresh(), $code];
    }

    private function credentials(Violation $record, string $code): array
    {
        return ['ticket_no' => $record->citation->ticket_no, 'access_code' => $code];
    }

    public function test_credentials_open_only_one_ticket_and_expose_only_status(): void
    {
        [$record, $code] = $this->record();
        [$other] = $this->record('paid');
        $this->post(route('search.lookup'), $this->credentials($record, $code))->assertRedirect(route('search.results'));
        $this->get(route('search.results'))->assertOk()->assertViewHas('record', fn ($row) => $row->id === $record->id)
            ->assertSee('Not Yet Verified')->assertSee($record->citation->ticket_no)
            ->assertDontSee($other->citation->ticket_no)->assertDontSee('Private home address')->assertDontSee('Citizen Test')
            ->assertDontSee('ABC-123')->assertDontSee('N01-123')->assertDontSee('Private offense')->assertDontSee('500.00')
            ->assertDontSee($code)->assertDontSee($record->public_access_hash)
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->assertStringNotContainsString($code, json_encode(session('public_record')));
        $this->assertArrayNotHasKey('public_access_hash', $record->toArray());
    }

    public function test_name_plate_license_and_ticket_alone_cannot_open_records(): void
    {
        [$record] = $this->record();
        foreach ([['query' => 'Citizen Test'], ['query' => 'ABC-123'], ['query' => 'N01-123'], ['ticket_no' => $record->citation->ticket_no]] as $input) {
            $this->post(route('search.lookup'), $input)->assertRedirect(route('search'))->assertSessionHasErrors('lookup')->assertSessionMissing('public_record');
        }
        $this->get(route('search.results'))->assertRedirect(route('search'));
    }

    public function test_failures_are_generic_and_never_flash_codes_or_other_inputs(): void
    {
        [$record, $code] = $this->record();
        $message = null;
        foreach ([['ticket_no' => 'UNKNOWN', 'access_code' => $code], $this->credentials($record, str_repeat('A', 32)), ['ticket_no' => [], 'access_code' => ['bad']]] as $input) {
            $this->post(route('search.lookup'), $input)->assertRedirect(route('search'))->assertSessionHasErrors('lookup')->assertSessionMissing('public_record');
            $current = session('errors')->first('lookup');
            $message ??= $current;
            $this->assertSame($message, $current);
            $this->assertEmpty(session()->getOldInput());
        }
    }

    public function test_code_formatting_is_tolerant_but_codes_are_bound_to_one_ticket(): void
    {
        [$record, $code] = $this->record();
        [$other] = $this->record('paid');
        $this->post(route('search.lookup'), $this->credentials($other, $code))->assertSessionHasErrors('lookup');
        $this->post(route('search.lookup'), $this->credentials($record, strtolower(str_replace('-', ' ', $code))))->assertRedirect(route('search.results'));
        $this->get(route('search.results'))->assertOk()->assertSee('Not Yet Verified');
    }

    public function test_sessions_expire_after_fifteen_minutes_and_closing_revokes_access(): void
    {
        [$record, $code] = $this->record();
        $this->post(route('search.lookup'), $this->credentials($record, $code));
        $this->travel(15)->minutes();
        $this->get(route('search.results'))->assertRedirect(route('search'))->assertSessionMissing('public_record');
        $this->travelBack();
        $this->post(route('search.lookup'), $this->credentials($record, $code));
        $this->post(route('search.forget'))->assertRedirect(route('search'));
        $this->get(route('search.results'))->assertRedirect(route('search'));
    }

    public function test_replacement_revokes_old_code_and_existing_sessions_and_deleted_records(): void
    {
        [$record, $code] = $this->record();
        $this->post(route('search.lookup'), $this->credentials($record, $code));
        $replacement = CitizenAccess::issue($record, 'Ticket holder lost the original code.');
        $this->get(route('search.results'))->assertRedirect(route('search'));
        $this->post(route('search.lookup'), $this->credentials($record, $code))->assertSessionHasErrors('lookup');
        $this->post(route('search.lookup'), $this->credentials($record, $replacement))->assertRedirect(route('search.results'));
        $record->violator->delete();
        $this->get(route('search.results'))->assertRedirect(route('search'));
    }

    public function test_legacy_public_sessions_and_records_without_codes_are_rejected(): void
    {
        [$record, $code] = $this->record();
        $this->withSession(['public_record' => ['name' => 'citizen test', 'reference' => 'ABC123', 'expires' => now()->addMinutes(15)->timestamp]])
            ->get(route('search.results'))->assertRedirect(route('search'));
        $record->update(['public_access_hash' => null]);
        $this->post(route('search.lookup'), $this->credentials($record, $code))->assertSessionHasErrors('lookup');
    }

    public function test_public_lookup_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) $this->post(route('search.lookup'), ['ticket_no' => 'unknown', 'access_code' => 'wrong'])->assertRedirect();
        $this->post(route('search.lookup'), ['ticket_no' => 'unknown', 'access_code' => 'wrong'])->assertStatus(429);
    }

    public function test_staff_issuance_requires_ownership_identity_check_and_reason_and_displays_code_once(): void
    {
        [$record, $oldCode] = $this->record();
        $url = route('violations.citizen-access', $record);
        $input = ['reason' => 'Identity verified at the POSO office.', 'identity_checked' => 1];
        $this->post($url, $input)->assertRedirect(route('login'));
        $this->actingAs($this->account())->post($url, $input)->assertForbidden();
        $this->actingAs($record->officer)->post($url, ['reason' => 'short'])->assertSessionHasErrors(['reason', 'identity_checked']);
        $this->post($url, $input)->assertRedirect(route('enforcer.show', $record));
        $issued = session('citizen_access');
        $this->assertNotSame($oldCode, $issued['code']);
        $this->assertSame(hash('sha256', CitizenAccess::normalize($issued['code'])), $record->fresh()->public_access_hash);
        $this->assertStringNotContainsString($issued['code'], AuditLog::latest('id')->first()->details);
        $this->get(route('enforcer.show', $record))->assertOk()->assertSee($issued['code'])->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('enforcer.show', $record))->assertOk()->assertDontSee($issued['code']);
        $this->actingAs($this->account('admin'))->post($url, $input)->assertRedirect(route('violations.show', $record));
    }

    public function test_citizen_host_has_private_lookup_and_privacy_notice_and_blocks_staff_routes(): void
    {
        [$record, $code] = $this->record('paid');
        $this->get(route('citizen.search'))->assertOk()->assertSee('Private access code')->assertSee('TOP (ticket number)')->assertDontSee('name="query"', false);
        $this->get(route('citizen.privacy'))->assertOk()->assertSee('Citizen portal privacy notice');
        $this->post(route('citizen.lookup'), $this->credentials($record, $code))->assertRedirect(route('citizen.results'));
        $this->get(route('citizen.results'))->assertOk()->assertSee('Settled');
        $this->get('http://'.config('portals.citizen_domain').'/login')->assertNotFound();
        $this->get('http://localhost/search/privacy')->assertOk();
    }

    public function test_https_is_required_when_enabled(): void
    {
        config(['portals.require_https' => true, 'session.secure' => true]);
        $this->get('http://portal.poso.test/')->assertStatus(308)->assertRedirect('https://portal.poso.test');
        $this->get('https://portal.poso.test/')->assertOk()->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }
}
