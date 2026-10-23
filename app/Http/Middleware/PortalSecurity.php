<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PortalSecurity
{
    public function handle(Request $request, Closure $next)
    {
        if (config('portals.require_https') && !$request->isSecure()) {
            return redirect()->to(preg_replace('/^http:/', 'https:', $request->fullUrl()), 308);
        }
        $response = $next($request);
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        if ($request->routeIs('citizen.*', 'search', 'search.*', 'violations.show', 'enforcer.show')) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }
        if ($request->isSecure() && config('portals.require_https')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        return $response;
    }
}
