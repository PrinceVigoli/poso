<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    /** A real bcrypt hash no password will match, used only to even out timing. */
    private const TIMING_EQUALISER = '$2y$12$eqZ1kFZzq0dQ0uVQ7yP9luXm5oBxYQ1xG6tGZ1H8lZ9V1mQ2sT7Hy';

    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->to($this->homeRoute(Auth::user()));
        }
        return view('auth.login');
    }

    protected function homeRoute(User $user): string
    {
        return $user->isEnforcer() ? route('enforcer.create') : route('dashboard');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Accounts are addressed by username; the guard authenticates by email.
        $user = User::where('username', $request->username)->first();

        // Verify the password BEFORE reporting anything about the account.
        // Checking is_active first told anyone with a junk password which
        // usernames exist. The dummy hash keeps an unknown username's response
        // time close to a known one's.
        $passwordMatches = $user
            ? Hash::check($request->password, $user->password)
            : Hash::check($request->password, self::TIMING_EQUALISER);

        if (! $user || ! $passwordMatches) {
            return back()->withErrors(['username' => 'Invalid username or password.'])->onlyInput('username');
        }

        if (! $user->is_active) {
            return back()->withErrors(['username' => 'Your account has been deactivated. Contact admin.'])->onlyInput('username');
        }

        // Attempt login using email (Laravel default guard uses email)
        if (Auth::attempt(['email' => $user->email, 'password' => $request->password], $request->boolean('remember'))) {
            $request->session()->regenerate();
            $request->session()->put('credential_version', Auth::user()->credential_version);
            return redirect()->to($this->homeRoute(Auth::user()));
        }

        return back()->withErrors(['username' => 'Invalid username or password.'])->onlyInput('username');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
