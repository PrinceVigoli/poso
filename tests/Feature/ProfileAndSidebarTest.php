<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileAndSidebarTest extends TestCase
{
    use RefreshDatabase;

    private const CURRENT = 'original-test-password';
    private const REPLACEMENT = 'a-brand-new-secure-password';

    private function account(string $role): User
    {
        $n = User::count();
        return User::create(['name' => 'Profile '.$role.' '.$n, 'username' => $role.$n, 'email' => $role.$n.'@example.test', 'password' => self::CURRENT, 'role' => $role]);
    }

    // ── Access ──────────────────────────────────────────────────────────

    public function test_guests_cannot_reach_the_profile_page(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }

    /** Enforcers need this as much as admins, or passwords get shared verbally. */
    public function test_both_admins_and_enforcers_can_open_their_own_profile(): void
    {
        foreach (['admin', 'enforcer'] as $role) {
            $user = $this->account($role);
            $this->actingAs($user)->get(route('profile.edit'))->assertOk()
                ->assertSee('Change password')
                ->assertSee($user->username)
                ->assertHeader('Cache-Control', 'no-store, private');
        }
    }

    // ── Name ────────────────────────────────────────────────────────────

    public function test_a_staff_member_can_change_their_own_name(): void
    {
        $user = $this->account('enforcer');
        $this->actingAs($user)->put(route('profile.update'), ['name' => 'Renamed Person'])
            ->assertRedirect(route('profile.edit'))->assertSessionHas('success');

        $this->assertSame('Renamed Person', $user->fresh()->name);
    }

    public function test_the_name_is_required(): void
    {
        $user = $this->account('admin');
        $this->actingAs($user)->put(route('profile.update'), ['name' => ''])->assertSessionHasErrors('name');
    }

    // ── Privilege ───────────────────────────────────────────────────────

    /** User::$fillable holds role and is_active, so the form must whitelist. */
    public function test_an_enforcer_cannot_promote_itself_through_the_profile_form(): void
    {
        $user = $this->account('enforcer');

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'Still An Enforcer',
            'role' => 'admin',
            'is_active' => false,
        ])->assertRedirect();

        $fresh = $user->fresh();
        $this->assertSame('enforcer', $fresh->role, 'Role must not be settable from the profile form.');
        $this->assertTrue($fresh->is_active, 'Activation must not be settable from the profile form.');
        $this->assertSame('Still An Enforcer', $fresh->name);
    }

    public function test_the_username_cannot_be_changed_here(): void
    {
        $user = $this->account('admin');
        $original = $user->username;

        $this->actingAs($user)->put(route('profile.update'), ['name' => 'Same Person', 'username' => 'hijacked'])->assertRedirect();
        $this->assertSame($original, $user->fresh()->username);
    }

    // ── Password ────────────────────────────────────────────────────────

    public function test_changing_the_password_requires_the_current_one(): void
    {
        $user = $this->account('admin');

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name, 'password' => self::REPLACEMENT, 'password_confirmation' => self::REPLACEMENT,
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check(self::CURRENT, $user->fresh()->password), 'Password must be unchanged.');
    }

    public function test_a_wrong_current_password_is_rejected(): void
    {
        $user = $this->account('admin');

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name, 'current_password' => 'not-my-password',
            'password' => self::REPLACEMENT, 'password_confirmation' => self::REPLACEMENT,
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check(self::CURRENT, $user->fresh()->password));
    }

    public function test_the_new_password_must_meet_the_fifteen_character_minimum(): void
    {
        $user = $this->account('admin');

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name, 'current_password' => self::CURRENT,
            'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }

    public function test_the_new_password_must_be_confirmed(): void
    {
        $user = $this->account('admin');

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name, 'current_password' => self::CURRENT,
            'password' => self::REPLACEMENT, 'password_confirmation' => 'something-else-entirely',
        ])->assertSessionHasErrors('password');
    }

    public function test_a_correct_change_replaces_the_password(): void
    {
        $user = $this->account('admin');

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name, 'current_password' => self::CURRENT,
            'password' => self::REPLACEMENT, 'password_confirmation' => self::REPLACEMENT,
        ])->assertRedirect(route('profile.edit'))->assertSessionHas('success');

        $this->assertTrue(Hash::check(self::REPLACEMENT, $user->fresh()->password));
    }

    /**
     * credential_version is bumped on a password change and
     * EnsureAccountIsActive signs out any session whose stored copy is stale.
     * Without re-seeding it, you are bounced to login on your next click.
     */
    public function test_changing_your_own_password_does_not_sign_you_out(): void
    {
        $user = $this->account('admin');

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name, 'current_password' => self::CURRENT,
            'password' => self::REPLACEMENT, 'password_confirmation' => self::REPLACEMENT,
        ])->assertRedirect();

        $this->get(route('dashboard'))->assertOk();
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_changing_the_password_raises_the_credential_version(): void
    {
        $user = $this->account('admin');
        $before = $user->credential_version;

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name, 'current_password' => self::CURRENT,
            'password' => self::REPLACEMENT, 'password_confirmation' => self::REPLACEMENT,
        ])->assertRedirect();

        $this->assertGreaterThan($before, $user->fresh()->credential_version, 'Other devices must be revoked.');
    }

    public function test_the_change_is_written_to_the_audit_log(): void
    {
        $user = $this->account('admin');
        $this->actingAs($user)->put(route('profile.update'), ['name' => 'Audited Name'])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['module' => 'users', 'user_id' => $user->id]);
    }

    // ── Collapsible sidebar ─────────────────────────────────────────────

    /** The account block sits in the sidebar header and opens the profile. */
    public function test_the_sidebar_account_block_links_to_the_profile(): void
    {
        $user = $this->account('admin');
        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<a href="[^"]*\/profile"[^>]*class="sb-user/',
            $html,
            'The account block must be a link to the profile.'
        );
        // It belongs above the navigation, not in the footer.
        $this->assertLessThan(strpos($html, 'class="sb-nav"'), strpos($html, 'class="sb-user'));
    }

    /** Collapsed to a rail there is no room for a word, so the toggle is icon-only. */
    public function test_the_collapse_toggle_is_icon_only_and_still_labelled(): void
    {
        $html = $this->actingAs($this->account('admin'))->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('sb-collapse-label', $html);
        $this->assertStringContainsString('aria-label="Collapse sidebar"', $html);
    }

    public function test_the_sidebar_offers_a_collapse_toggle(): void
    {
        $html = $this->actingAs($this->account('admin'))->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('id="sidebarCollapseBtn"', $html);
        $this->assertStringContainsString('aria-controls="sidebar"', $html);
        $this->assertStringContainsString('aria-expanded="true"', $html);
    }

    /** Applied before paint, or a collapsed rail flashes open on every load. */
    public function test_the_stored_choice_is_applied_before_the_page_paints(): void
    {
        $html = $this->actingAs($this->account('admin'))->get(route('dashboard'))->assertOk()->getContent();

        $bodyAt = strpos($html, '<body>');
        $scriptAt = strpos($html, "localStorage.getItem('poso.sidebar')");
        $sidebarAt = strpos($html, 'id="sidebar"');

        $this->assertNotFalse($scriptAt, 'The pre-paint script is missing.');
        $this->assertGreaterThan($bodyAt, $scriptAt);
        $this->assertLessThan($sidebarAt, $scriptAt, 'The script must run before the sidebar renders.');
    }

    /** Labels are hidden in the rail, so each icon needs its name as a tooltip. */
    public function test_every_sidebar_link_carries_its_label_as_a_tooltip(): void
    {
        $html = $this->actingAs($this->account('admin'))->get(route('dashboard'))->assertOk()->getContent();

        foreach (['Dashboard', 'Violators', 'Violations', 'Reports', 'User Management', 'Offense Types', 'Audit Logs'] as $label) {
            $this->assertStringContainsString('title="'.$label.'"', $html, "The {$label} link has no tooltip.");
        }
    }
}
