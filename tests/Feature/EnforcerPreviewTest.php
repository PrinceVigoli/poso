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

class EnforcerPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function setupEnforcer(): array
    {
        $user = User::create(['name' => 'Enforcer', 'username' => 'enforcer', 'email' => 'enforcer@example.test', 'password' => 'password', 'role' => 'enforcer']);
        $this->actingAs($user);
        $type = ViolationType::create(['offense_name' => 'Illegal parking', 'fine_amount' => 500]);
        return ['location' => 'Poblacion checkpoint', 'top_number' => 'TOP-001', 'full_name' => 'Juan Dela Cruz', 'violation_type_id' => $type->id, 'license_no' => 'N01-123', 'vehicle_plate' => 'ABC 123', 'vehicle_type' => 'Motorcycle', 'contact_no' => '09171234567', 'address' => 'Luna, Apayao', 'birthdate' => '1990-01-02', 'confiscated_id' => 'Student ID', 'additional_info' => 'Presented school identification.'];
    }

    public function test_preview_saves_nothing_and_confirmation_saves_reviewed_details_once(): void
    {
        $input = $this->setupEnforcer();
        $this->get(route('enforcer.create'))->assertOk()->assertSee('Preview details')->assertSee('POSO Enforcer')->assertDontSee('id="sidebar"', false);
        $this->post(route('enforcer.preview'), $input)->assertRedirect(route('enforcer.review'));
        $this->get(route('enforcer.review'))->assertOk()->assertSee('ABC 123')->assertSee('Luna, Apayao')->assertSee('Poblacion checkpoint')->assertSee('Confirm and submit')->assertDontSee('id="sidebar"', false);
        $this->assertSame(0, Violator::count());
        $this->assertSame(0, Violation::count());
        $this->assertSame(0, Citation::count());
        $this->assertSame(0, AuditLog::count());
        $token = session('enforcer_preview.token');

        // Extra posted values must not replace the reviewed session data.
        $this->post(route('enforcer.confirm'), ['preview_token' => $token, 'confirmed' => 1, 'full_name' => 'Tampered Name', 'fine_amount' => 1, 'location' => 'Tampered location'])->assertRedirect();
        $this->assertDatabaseHas('violators', ['full_name' => 'Juan Dela Cruz', 'vehicle_plate' => 'ABC 123', 'license_no' => 'N01-123']);
        $this->assertDatabaseHas('citations', ['fine_amount' => 500, 'payment_status' => 'pending']);
        $this->assertDatabaseHas('violations', ['location' => 'Poblacion checkpoint', 'confiscated_id' => 'Student ID', 'remarks' => 'Presented school identification.']);
        $this->assertSame(1, Violation::count());
        $this->assertNull(session('enforcer_preview'));
        $this->get(route('violations.show', Violation::first()))->assertOk();
        $this->get(route('enforcer.show', Violation::first()))->assertOk()->assertViewIs('violations.enforcer_submitted')->assertSee('Record another violation')->assertSee('Poblacion checkpoint')->assertDontSee('id="sidebar"', false);
        $this->post(route('enforcer.confirm'), ['preview_token' => $token, 'confirmed' => 1])->assertRedirect(route('enforcer.create'));
        $this->assertSame(1, Citation::count());
    }

    public function test_edit_preserves_inputs_and_invalidates_old_preview(): void
    {
        $input = $this->setupEnforcer();
        $this->post(route('enforcer.preview'), $input);
        $token = session('enforcer_preview.token');
        $this->post(route('enforcer.edit'))->assertRedirect(route('enforcer.create'))->assertSessionHasInput('vehicle_plate', 'ABC 123')->assertSessionHasInput('birthdate', '1990-01-02')->assertSessionHasInput('location', 'Poblacion checkpoint');
        $this->assertNull(session('enforcer_preview'));
        $this->post(route('enforcer.confirm'), ['preview_token' => $token, 'confirmed' => 1])->assertRedirect(route('enforcer.create'));
        $this->assertSame(0, Violator::count());
    }

    public function test_confirmation_requires_valid_preview_checkbox_and_unexpired_token(): void
    {
        $input = $this->setupEnforcer();
        $this->post(route('enforcer.confirm'), ['confirmed' => 1])->assertSessionHasErrors('preview_token');
        $this->post(route('enforcer.preview'), $input);
        $token = session('enforcer_preview.token');
        $this->post(route('enforcer.confirm'), ['preview_token' => $token])->assertSessionHasErrors('confirmed');
        $this->post(route('enforcer.confirm'), ['preview_token' => 'wrong', 'confirmed' => 1])->assertRedirect(route('enforcer.create'));
        $this->travel(31)->minutes();
        $this->post(route('enforcer.confirm'), ['preview_token' => $token, 'confirmed' => 1])->assertRedirect(route('enforcer.create'));
        $this->travelBack();
        $this->assertSame(0, Citation::count());
        $this->assertSame(0, Violator::count());
    }

    public function test_existing_archived_profile_keeps_history_and_blank_fields(): void
    {
        $input = $this->setupEnforcer();
        $person = Violator::create(['full_name' => $input['full_name'], 'address' => 'Existing address']);
        $old = Violation::create(['violator_id' => $person->id, 'officer_id' => auth()->id(), 'violation_type_id' => $input['violation_type_id'], 'violation_date' => today()->subDay(), 'location' => 'Luna', 'status' => 'settled']);
        Citation::create(['violation_id' => $old->id, 'ticket_no' => 'OLD-001', 'fine_amount' => 500, 'due_date' => today(), 'payment_status' => 'paid']);
        $input['address'] = 'Existing address';
        $input['matched_violator_id'] = $person->id;
        $this->post(route('enforcer.preview'), $input)->assertRedirect(route('enforcer.review'));
        $this->get(route('enforcer.review'))->assertSee('Existing address');
        $this->assertNull($person->fresh()->vehicle_plate);
        $this->post(route('enforcer.confirm'), ['preview_token' => session('enforcer_preview.token'), 'confirmed' => 1])->assertRedirect();
        $this->assertSame(1, Violator::count());
        $this->assertSame(2, $person->violations()->count());
        $this->assertSame('Existing address', $person->fresh()->address);
        $this->assertFalse($person->isArchived());
    }

    public function test_match_and_duplicate_warnings_preserve_details_and_still_require_preview(): void
    {
        $input = $this->setupEnforcer();
        $person = Violator::create(['full_name' => 'Juan Dela Cruz']);
        Violation::create(['violator_id' => $person->id, 'officer_id' => auth()->id(), 'violation_type_id' => $input['violation_type_id'], 'violation_date' => today(), 'location' => 'Luna']);
        $input['full_name'] = 'Juan Dela Crux';
        $this->post(route('enforcer.preview'), $input)->assertOk()->assertViewIs('violations.confirm_match')->assertSee('ABC 123')->assertSee('Poblacion checkpoint');
        $input['matched_violator_id'] = $person->id;
        $this->post(route('enforcer.preview'), $input)->assertOk()->assertViewIs('violations.confirm_duplicate')->assertSee('ABC 123')->assertSee('Poblacion checkpoint');
        $input['confirm_duplicate'] = 1;
        $this->post(route('enforcer.preview'), $input)->assertRedirect(route('enforcer.review'));
        $this->assertSame(1, Violation::count());
        $this->assertSame(0, Citation::count());
        $this->post(route('enforcer.confirm'), ['preview_token' => session('enforcer_preview.token'), 'confirmed' => 1])->assertRedirect();
        $this->assertSame(2, Violation::count());
        $this->assertSame(1, Violator::count());
        $this->assertNull($person->fresh()->vehicle_plate);
        $this->assertSame('ABC 123', Violation::latest('id')->first()->personDetail('vehicle_plate'));
    }

    public function test_old_submit_endpoint_also_requires_preview_and_other_roles_cannot_confirm(): void
    {
        $input = $this->setupEnforcer();
        $this->post(route('violations.store'), $input)->assertRedirect(route('enforcer.review'));
        $this->assertSame(0, Violation::count());
        auth()->user()->update(['role' => 'admin']);
        $this->withSession(['credential_version' => auth()->user()->credential_version]);
        $this->post(route('enforcer.confirm'), ['preview_token' => session('enforcer_preview.token'), 'confirmed' => 1])->assertForbidden();
        $this->assertSame(0, Violation::count());
    }

    public function test_invalid_details_and_changed_fine_do_not_save_records(): void
    {
        $input = $this->setupEnforcer();
        $this->post(route('enforcer.preview'), array_merge($input, ['birthdate' => today()->addDay()->toDateString()]))->assertSessionHasErrors('birthdate');
        $this->assertNull(session('enforcer_preview'));
        $this->post(route('enforcer.preview'), $input);
        ViolationType::findOrFail($input['violation_type_id'])->update(['fine_amount' => 700]);
        $this->post(route('enforcer.confirm'), ['preview_token' => session('enforcer_preview.token'), 'confirmed' => 1])->assertSessionHasErrors('preview');
        $this->assertSame(0, Violator::count());
        $this->assertSame(0, Violation::count());
        $this->assertSame(0, Citation::count());
    }

    public function test_choosing_new_person_after_match_warning_still_saves_nothing_until_confirmation(): void
    {
        $input = $this->setupEnforcer();
        Violator::create(['full_name' => 'Juan Dela Crux']);
        $this->post(route('enforcer.preview'), $input)->assertViewIs('violations.confirm_match');
        $this->post(route('enforcer.preview'), array_merge($input, ['confirm_new' => 1]))->assertRedirect(route('enforcer.review'));
        $this->assertSame(1, Violator::count());
        $this->assertSame(0, Citation::count());
        $this->get(route('enforcer.create'))->assertOk();
        $this->assertNotNull(session('enforcer_preview'));
        $this->post(route('enforcer.clear'))->assertRedirect(route('enforcer.create'));
        $this->assertNull(session('enforcer_preview'));
    }

    public function test_required_details_and_id_options_are_validated_and_optional_fields_can_be_blank(): void
    {
        $input = $this->setupEnforcer();
        $this->post(route('enforcer.preview'), array_merge($input, ['full_name' => '', 'address' => '', 'location' => '', 'confiscated_id' => 'Unknown ID']))
            ->assertSessionHasErrors(['full_name', 'address', 'location', 'confiscated_id']);
        $this->post(route('enforcer.preview'), array_merge($input, ['location' => str_repeat('x', 256)]))->assertSessionHasErrors('location');
        $this->assertSame(0, Violation::count());
        $this->post(route('enforcer.preview'), array_merge($input, ['vehicle_plate' => '', 'additional_info' => '', 'confiscated_id' => 'None']))
            ->assertRedirect(route('enforcer.review'));
        $this->get(route('enforcer.review'))->assertOk()->assertSee('ID confiscated')->assertSee('None')->assertSee('Not provided');
        $this->post(route('enforcer.confirm'), ['preview_token' => session('enforcer_preview.token'), 'confirmed' => 1, 'confiscated_id' => 'Company ID', 'additional_info' => 'Tampered'])
            ->assertRedirect();
        $this->assertDatabaseHas('violations', ['confiscated_id' => 'None', 'remarks' => null]);
        $this->assertDatabaseHas('violators', ['vehicle_plate' => null]);
    }
}
