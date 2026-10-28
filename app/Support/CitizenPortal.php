<?php

namespace App\Support;

class CitizenPortal
{
    public static function searchUrl(): string
    {
        // The development server needs no separate citizen hostname or TLS setup.
        $route = in_array(request()->getHost(), ['localhost', '127.0.0.1', '[::1]', '::1'], true)
            ? 'search'
            : 'citizen.search';

        return route($route);
    }
}
