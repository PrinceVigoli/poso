<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        // A valid persistent login authenticates against the newly rotated token.
        if ($user && Auth::viaRemember()) {
            $request->session()->put('credential_version', $user->credential_version);
        }
        if ($user && (!$user->is_active || (int) $request->session()->get('credential_version', 0) !== $user->credential_version)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'username' => $user->is_active ? 'Your access details changed. Please sign in again.' : 'Your account has been deactivated. Contact admin.',
            ]);
        }

        return $next($request);
    }
}
