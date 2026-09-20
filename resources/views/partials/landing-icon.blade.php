@php
    $paths = match ($icon) {
        'shield' => ['M12 3 3 7v6c0 5 9 9 9 9s9-4 9-9V7l-9-4Z', 'm8 12 3 3 5-6'],
        'arrow-up-right' => ['M7 17 17 7M7 7h10v10'],
        'arrow-right' => ['M4 12h16m-6-6 6 6-6 6'],
        'person' => ['M20 21v-2a7 7 0 0 0-14 0v2', 'M17 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0'],
        'calendar3' => ['M8 2v4m8-4v4M3 10h18', 'M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2'],
        'bank' => ['M3 9h18L12 3 3 9Zm3 3v7m6-7v7m6-7v7M3 22h18'],
        'file-earmark-check' => ['M14 2H4v20h16V8l-6-6Zm0 0v6h6M8 14l3 3 5-5'],
        'info-circle' => ['M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0', 'M12 11v6m0-10v.5'],
        default => ['M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0', 'm8 12 3 3 5-6'],
    };
@endphp
<svg class="landing-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">@foreach($paths as $path)<path d="{{ $path }}"/>@endforeach</svg>
