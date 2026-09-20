<?php

namespace Tests\Feature;

use App\Models\Violator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalDomainsTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_site_has_landing_and_login_and_localhost_still_works(): void
    {
        $this->get('http://poso-portal.test/')->assertOk()->assertViewIs('landing')
            ->assertSee('http://portal.poso.test', false)->assertDontSee('Staff sign in')->assertDontSee('http://poso-portal.test/login', false);
        $this->get('http://poso-portal.test/login')->assertOk()->assertSee('Welcome back');
        $this->get('http://localhost/')->assertOk()->assertViewIs('landing');
        $this->get('http://localhost/search')->assertOk()->assertViewIs('search.index');
    }

    public function test_citizen_host_keeps_search_links_and_records_on_its_domain(): void
    {
        $user = \App\Models\User::create(['name' => 'Enforcer', 'username' => 'domain-user', 'email' => 'domain@example.test', 'password' => 'password', 'role' => 'enforcer']);
        $person = Violator::create(['full_name' => 'Domain Test Citizen', 'address' => 'Private address']);
        $v = \App\Models\Violation::create(['violator_id' => $person->id, 'officer_id' => $user->id, 'violation_type_id' => \App\Models\ViolationType::firstOrFail()->id, 'violation_date' => today()]);
        $this->get('http://portal.poso.test/')->assertOk()->assertViewIs('search.index')->assertDontSee('Staff sign in')->assertSee('action="http://portal.poso.test/lookup"', false);
        $this->get('http://portal.poso.test/?q=Domain')->assertOk()->assertDontSee('Domain Test Citizen');
        $this->post('http://portal.poso.test/lookup', ['query' => $person->full_name])->assertRedirect('http://portal.poso.test/records');
        $this->get('http://portal.poso.test/records')->assertOk()->assertSee('Settlement status')->assertDontSee('Private address')->assertDontSee('Staff sign in');
    }
    public function test_citizen_host_does_not_expose_staff_pages(): void
    {
        foreach (['/login', '/dashboard', '/admin/users', '/enforcer/record'] as $path) {
            $this->get('http://portal.poso.test'.$path)->assertNotFound();
        }
    }
}
