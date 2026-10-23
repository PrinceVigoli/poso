<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MakeUserCommandTest extends TestCase
{
    use RefreshDatabase;

    private const STRONG = 'a-long-enough-password';

    private function make(array $options = []): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('poso:make-user', array_merge([
            '--name'     => 'Console Made Person',
            '--username' => 'consoleuser',
            '--email'    => 'console@poso.gov.ph',
            '--role'     => 'admin',
            '--password' => self::STRONG,
        ], $options));
    }

    /** On a fresh database nobody can sign in, so the first admin comes from here. */
    public function test_it_creates_an_account_that_can_sign_in(): void
    {
        $this->make()->expectsConfirmation('Create this account?', 'yes')->assertSuccessful();

        $user = User::where('username', 'consoleuser')->first();
        $this->assertNotNull($user);
        $this->assertSame('admin', $user->role);
        $this->assertTrue($user->is_active);

        $this->post(route('login.submit'), ['username' => 'consoleuser', 'password' => self::STRONG])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_the_password_is_stored_hashed(): void
    {
        $this->make()->expectsConfirmation('Create this account?', 'yes')->assertSuccessful();

        $stored = User::where('username', 'consoleuser')->value('password');
        $this->assertNotSame(self::STRONG, $stored, 'The plain password must never be stored.');
        $this->assertTrue(Hash::check(self::STRONG, $stored));
    }

    public function test_it_can_create_an_enforcer(): void
    {
        $this->make(['--role' => 'enforcer'])->expectsConfirmation('Create this account?', 'yes')->assertSuccessful();

        $this->assertSame('enforcer', User::where('username', 'consoleuser')->value('role'));
    }

    // ── It must not be a way around the browser's rules ──────────────────

    /** AdminController requires min:15; the console must not be the soft option. */
    public function test_it_refuses_a_password_below_the_fifteen_character_minimum(): void
    {
        $this->make(['--password' => 'short-one'])->assertFailed();

        $this->assertDatabaseMissing('users', ['username' => 'consoleuser']);
    }

    public function test_it_refuses_a_role_the_application_does_not_use(): void
    {
        // The users table enum still carries a legacy 'officer' value.
        $this->make(['--role' => 'officer'])->assertFailed();

        $this->assertDatabaseMissing('users', ['username' => 'consoleuser']);
    }

    public function test_it_refuses_a_duplicate_username(): void
    {
        User::create([
            'name' => 'Already Here', 'username' => 'consoleuser',
            'email' => 'taken@poso.gov.ph', 'password' => self::STRONG, 'role' => 'enforcer',
        ]);

        $this->make()->assertFailed();

        $this->assertSame(1, User::where('username', 'consoleuser')->count());
        $this->assertSame('Already Here', User::where('username', 'consoleuser')->value('name'));
    }

    public function test_it_refuses_a_duplicate_email(): void
    {
        User::create([
            'name' => 'Already Here', 'username' => 'someoneelse',
            'email' => 'console@poso.gov.ph', 'password' => self::STRONG, 'role' => 'enforcer',
        ]);

        $this->make()->assertFailed();

        $this->assertDatabaseMissing('users', ['username' => 'consoleuser']);
    }

    public function test_it_refuses_a_malformed_email(): void
    {
        $this->make(['--email' => 'not-an-address'])->assertFailed();

        $this->assertDatabaseMissing('users', ['username' => 'consoleuser']);
    }

    // ── Cancelling ───────────────────────────────────────────────────────

    public function test_answering_no_creates_nothing(): void
    {
        $this->make()->expectsConfirmation('Create this account?', 'no')->assertSuccessful();

        $this->assertDatabaseMissing('users', ['username' => 'consoleuser']);
    }

    // ── Audit trail ──────────────────────────────────────────────────────

    public function test_the_new_account_is_written_to_the_audit_log(): void
    {
        $this->make()->expectsConfirmation('Create this account?', 'yes')->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', ['module' => 'users', 'action' => 'created']);
    }
}
