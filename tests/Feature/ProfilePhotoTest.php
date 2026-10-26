<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role = 'admin'): User
    {
        $n = User::count();
        return User::create(['name' => 'Photo Staff '.$n, 'username' => 'photo'.$n, 'email' => 'photo'.$n.'@example.test', 'password' => 'a-long-enough-password', 'role' => $role]);
    }

    public function test_each_role_can_upload_a_photo_and_see_a_circular_avatar(): void
    {
        Storage::fake('local');
        foreach (['admin', 'enforcer'] as $role) {
            $user = $this->account($role);
            $this->actingAs($user)->post(route('profile.update'), ['_method' => 'PUT', 'name' => $user->name, 'profile_photo' => UploadedFile::fake()->image('photo.png', 640, 360)])
                ->assertRedirect(route('profile.edit'))->assertSessionHasNoErrors();
            $path = $user->fresh()->profile_photo_path;
            Storage::disk('local')->assertExists($path);
            $size = getimagesizefromstring(Storage::disk('local')->get($path));
            $this->assertSame([256, 256], [$size[0], $size[1]]);
            $this->assertSame('image/jpeg', $size['mime']);
            $this->assertSame(0, $user->fresh()->credential_version);
            $this->get(route('profile.photo'))->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('Cache-Control', 'no-store, private');
            $this->get(route('profile.edit'))->assertOk()->assertSee('enctype="multipart/form-data"', false)->assertSee('border-radius:50%', false)->assertSee('/profile/photo', false);
            $this->get(route($role === 'admin' ? 'dashboard' : 'enforcer.index'))->assertOk()->assertSee('/profile/photo', false);
        }
    }

    public function test_replacement_and_removal_delete_the_old_photo(): void
    {
        Storage::fake('local');
        $user = $this->account();
        $this->actingAs($user);
        $paths = [];
        foreach (['first.jpg', 'second.png'] as $file) {
            $this->put(route('profile.update'), ['name' => $user->name, 'profile_photo' => UploadedFile::fake()->image($file)])->assertSessionHasNoErrors();
            $paths[] = $user->fresh()->profile_photo_path;
        }
        $this->assertNotSame($paths[0], $paths[1]);
        Storage::disk('local')->assertMissing($paths[0]);
        $this->put(route('profile.update'), ['name' => $user->name, 'remove_photo' => 1])->assertRedirect();
        $this->assertNull($user->fresh()->profile_photo_path);
        Storage::disk('local')->assertMissing($paths[1]);
        $this->get(route('profile.photo'))->assertNotFound();
    }

    public function test_invalid_photos_do_not_change_the_profile_or_leave_files(): void
    {
        Storage::fake('local');
        $user = $this->account();
        $this->actingAs($user);
        foreach ([UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml'), UploadedFile::fake()->image('large.jpg')->size(2049), UploadedFile::fake()->image('wide.png', 3001, 20)] as $file) {
            $this->put(route('profile.update'), ['name' => 'Should not save', 'profile_photo' => $file])->assertSessionHasErrors('profile_photo');
            $this->assertSame('Photo Staff 0', $user->fresh()->name);
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_photo_access_is_private_and_uploads_cannot_target_another_account(): void
    {
        Storage::fake('local');
        $owner = $this->account();
        $other = $this->account('enforcer');
        $this->get(route('profile.photo'))->assertRedirect(route('login'));
        $this->actingAs($owner)->put(route('profile.update'), ['name' => $owner->name, 'profile_photo' => UploadedFile::fake()->image('avatar.jpg')])->assertRedirect();
        $this->actingAs($other)->get(route('profile.photo', ['user_id' => $owner->id]))->assertNotFound();
        $this->put(route('profile.update'), ['name' => $other->name, 'user_id' => $owner->id, 'profile_photo_path' => $owner->fresh()->profile_photo_path, 'role' => 'admin'])->assertRedirect();
        $this->assertNull($other->fresh()->profile_photo_path);
        $this->assertSame('enforcer', $other->fresh()->role);
    }
}
