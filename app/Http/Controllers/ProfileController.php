<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Support\ProfilePhoto;

/**
 * Lets a signed-in staff member change their own display name and password.
 * Open to admins and enforcers alike: an enforcer who cannot change their own
 * password has to ask an admin to reset it, which means passwords get shared
 * out loud.
 */
class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return response()->view('profile.edit', ['user' => $request->user()])
            ->header('Cache-Control', 'no-store, private');
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => 'nullable|string|min:15|max:128|confirmed',
            'profile_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048|dimensions:max_width=3000,max_height=3000',
            'remove_photo' => 'nullable|boolean',
        ], [
            'current_password.current_password' => 'That is not your current password.',
            'current_password.required_with' => 'Enter your current password to set a new one.',
            'password.min' => 'A new password must be at least 15 characters.',
        ]);

        // Only explicitly selected fields reach the model. User::$fillable also
        // holds role and is_active, so a wider update would turn this form
        // into a way for any staff member to promote themselves to admin.
        $changes = ['name' => $data['name']];
        $changingPassword = filled($data['password'] ?? null);
        if ($changingPassword) {
            $changes['password'] = $data['password'];
        }

        $oldPhoto = $user->profile_photo_path;
        $newPhoto = $request->hasFile('profile_photo') ? ProfilePhoto::store($request->file('profile_photo')) : null;
        if ($newPhoto || $request->boolean('remove_photo')) {
            $user->profile_photo_path = $newPhoto;
        }
        try {
            $user->fill($changes)->save();
        } catch (\Throwable $exception) {
            if ($newPhoto) Storage::disk('local')->delete($newPhoto);
            throw $exception;
        }
        if ($oldPhoto && $oldPhoto !== $user->profile_photo_path) Storage::disk('local')->delete($oldPhoto);

        if ($changingPassword) {
            // Changing a password bumps credential_version, and
            // EnsureAccountIsActive signs out any session whose stored version
            // no longer matches. Without re-seeding it here, the person who
            // just changed their password is bounced to the login screen on
            // their very next click. Other devices still get signed out.
            $request->session()->regenerate();
            $request->session()->put('credential_version', $user->fresh()->credential_version);
        }

        AuditLog::record('updated', 'users', "Updated own profile: {$user->username}"
            .($changingPassword ? ' (password changed)' : ''));

        return redirect()->route('profile.edit')->with('success', $changingPassword
            ? 'Profile updated. Your new password is active and any other signed-in devices have been signed out.'
            : 'Profile updated.');
    }

    public function photo(Request $request)
    {
        $path = $request->user()->profile_photo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->response($path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
