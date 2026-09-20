<?php
namespace Tests\Feature;

use App\Models\{Citation, User, Violation, ViolationType, Violator};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CitizenPortalTest extends TestCase
{
    use RefreshDatabase;

    private function record(string $name = 'Citizen Test', string $status = 'pending', ?string $license = null): Violation
    {
        $user = User::create(['name' => 'Enforcer', 'username' => 'enforcer'.User::count(), 'email' => 'e'.User::count().'@example.test', 'password' => 'password', 'role' => 'enforcer']);
        $person = Violator::firstOrCreate(['full_name' => $name], ['address' => 'Private home address', 'vehicle_plate' => 'ABC-123', 'license_no' => $license]);
        $v = Violation::create(['violator_id' => $person->id, 'officer_id' => $user->id, 'violation_type_id' => ViolationType::firstOrFail()->id, 'violation_date' => today()]);
        Citation::create(['violation_id' => $v->id, 'ticket_no' => Citation::generateTicketNo(), 'fine_amount' => 500, 'due_date' => today(), 'payment_status' => $status]);
        return $v;
    }

    public function test_citizen_searches_by_name_alone_and_sees_settlement_status(): void
    {
        $this->record();
        $this->post(route('search.lookup'), ['query' => 'citizen test'])->assertRedirect(route('search.results'));
        $this->get(route('search.results'))->assertOk()
            ->assertSee('Settlement status')
            ->assertSee('Not Yet Verified')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_name_matching_ignores_case_and_extra_spacing(): void
    {
        $this->record();
        $this->post(route('search.lookup'), ['query' => '  CITIZEN   TEST  '])->assertRedirect(route('search.results'));
        $this->get(route('search.results'))->assertOk()->assertSee('Not Yet Verified');
    }

    /** All of one person's records are listed, so they can see what is still open. */
    public function test_every_record_under_the_name_is_listed(): void
    {
        $this->record();
        $settled = $this->record('Citizen Test', 'paid');
        $settled->update(['status' => 'settled']);

        $this->post(route('search.lookup'), ['query' => 'Citizen Test'])->assertRedirect();
        $this->get(route('search.results'))->assertOk()
            ->assertSee('Not Yet Verified')
            ->assertSee('Settled')
            ->assertSee('2 records were recorded under this name');
    }

    public function test_an_unknown_name_returns_no_records_rather_than_an_error(): void
    {
        $this->record();
        $this->post(route('search.lookup'), ['query' => 'Nobody At All'])->assertRedirect(route('search.results'));
        $this->get(route('search.results'))->assertOk()
            ->assertSee('No violation record was found under that name')
            ->assertDontSee('Not Yet Verified');
    }

    /** Only settlement status is public. Address, plate and fine are not. */
    public function test_results_never_expose_address_plate_or_fine_amount(): void
    {
        $this->record();
        $this->post(route('search.lookup'), ['query' => 'Citizen Test']);
        $this->get(route('search.results'))->assertOk()
            ->assertDontSee('Private home address')
            ->assertDontSee('ABC-123')
            ->assertDontSee('500.00');
    }

    public function test_results_require_a_search_and_expire_after_fifteen_minutes(): void
    {
        $this->record();
        $this->get(route('search.results'))->assertRedirect(route('search'));

        $this->post(route('search.lookup'), ['query' => 'Citizen Test'])->assertRedirect();
        $this->get(route('search.results'))->assertOk();

        $this->travel(16)->minutes();
        $this->get(route('search.results'))->assertRedirect(route('search'))->assertSessionHasErrors('lookup');
        $this->travelBack();
    }

    public function test_closing_the_records_clears_the_search(): void
    {
        $this->record();
        $this->post(route('search.lookup'), ['query' => 'Citizen Test']);
        $this->post(route('search.forget'))->assertRedirect(route('search'));
        $this->get(route('search.results'))->assertRedirect(route('search'));
    }

    public function test_a_blank_name_is_rejected(): void
    {
        $this->post(route('search.lookup'), ['query' => '   '])->assertSessionHasErrors();
    }

    public function test_search_form_no_longer_asks_for_an_access_code(): void
    {
        $this->get(route('search'))->assertOk()
            ->assertSee("Full name, licence number or plate number", false)
            ->assertDontSee('access_code')
            ->assertDontSee('Private access code');
    }

    public function test_citizen_can_search_by_licence_number(): void
    {
        $this->record('Licenced Driver', 'pending', 'N01-23-456789');
        $this->post(route('search.lookup'), ['query' => 'N01-23-456789'])->assertRedirect(route('search.results'));
        $this->get(route('search.results'))->assertOk()->assertSee('Not Yet Verified');
    }

    /** Licences are written inconsistently, so spacing, hyphens and case are ignored. */
    public function test_licence_matching_ignores_hyphens_spacing_and_case(): void
    {
        $this->record('Licenced Driver', 'pending', 'N01-23-456789');
        foreach (['n0123456789', 'N01 23 456789', '  n01-23-456789  '] as $typed) {
            $this->post(route('search.lookup'), ['query' => $typed])->assertRedirect(route('search.results'));
            $this->get(route('search.results'))->assertOk()->assertSee('Not Yet Verified');
        }
    }

    /** A licence identifies one person, so it must not pull in a namesake's records. */
    public function test_licence_search_returns_only_that_persons_records(): void
    {
        $this->record('Shared Name', 'pending', 'AAA-111');

        // A genuinely different person who happens to share the name.
        $namesake = Violator::create(['full_name' => 'Shared Name', 'address' => 'Another address']);
        $officer = User::create(['name' => 'Enforcer', 'username' => 'twin', 'email' => 'twin@example.test', 'password' => 'password', 'role' => 'enforcer']);
        $twin = Violation::create(['violator_id' => $namesake->id, 'officer_id' => $officer->id, 'violation_type_id' => ViolationType::firstOrFail()->id, 'violation_date' => today()]);
        Citation::create(['violation_id' => $twin->id, 'ticket_no' => Citation::generateTicketNo(), 'fine_amount' => 500, 'due_date' => today(), 'payment_status' => 'pending']);

        $this->post(route('search.lookup'), ['query' => 'AAA-111']);
        $this->get(route('search.results'))->assertOk()->assertDontSee('records were recorded under this name');

        $this->post(route('search.lookup'), ['query' => 'Shared Name']);
        $this->get(route('search.results'))->assertOk()->assertSee('2 records were recorded under this name');
    }

    /** Violators without a licence must not all match one another on a blank value. */
    public function test_violators_without_a_licence_do_not_match_each_other(): void
    {
        $this->record('Person Without Licence A');
        $this->record('Person Without Licence B');

        $this->assertNull(Violator::where('full_name', 'Person Without Licence A')->first()->normalized_license);
        $this->post(route('search.lookup'), ['query' => 'Person Without Licence A']);
        $this->get(route('search.results'))->assertOk()
            ->assertDontSee('records were recorded under this name');
    }

    public function test_citizen_can_search_by_plate_number(): void
    {
        $this->record();   // fixture carries plate ABC-123
        $this->post(route('search.lookup'), ['query' => 'ABC-123'])->assertRedirect(route('search.results'));
        $this->get(route('search.results'))->assertOk()->assertSee('Not Yet Verified');
    }

    public function test_plate_matching_ignores_hyphens_spacing_and_case(): void
    {
        $this->record();
        foreach (['abc123', 'ABC 123', '  abc-123  '] as $typed) {
            $this->post(route('search.lookup'), ['query' => $typed])->assertRedirect(route('search.results'));
            $this->get(route('search.results'))->assertOk()->assertSee('Not Yet Verified');
        }
    }

    /** Blank plates must not make every plate-less violator match each other. */
    public function test_violators_without_a_plate_do_not_match_each_other(): void
    {
        $bare = Violator::create(['full_name' => 'No Vehicle Person', 'address' => 'Somewhere']);
        $this->assertNull($bare->fresh()->normalized_plate);
        $this->assertNull($bare->fresh()->normalized_license);
    }

    public function test_login_has_public_search_link_and_does_not_display_default_credentials(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Welcome back')->assertSee('Check Settlement Status')->assertDontSee('Password for all');
    }
}
