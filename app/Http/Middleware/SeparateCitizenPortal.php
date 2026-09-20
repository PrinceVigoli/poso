<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SeparateCitizenPortal
{
    public function handle(Request $request, Closure $next)
    {
        // Staff routes stay on the main site; the citizen host is search-only.
        if ($request->getHost() === config('portals.citizen_domain')) {
            abort_unless($request->routeIs('citizen.*'), 404);
        }

        return $next($request);
    }
}
