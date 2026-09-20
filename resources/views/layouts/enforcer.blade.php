<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>POSO Enforcer &mdash; @yield('title', 'Record Violator')</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.css') }}">
    <link href="{{ asset('vendor/fonts/fonts.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/portal-base.css') }}?v={{ filemtime(public_path('css/portal-base.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/enforcer.css') }}?v={{ filemtime(public_path('css/enforcer.css')) }}">
    @stack('styles')
</head>
<body class="enforcer-page">
    <a class="enforcer-skip" href="#enforcer-content">Skip to form</a>
    <header class="enforcer-header">
        <div class="enforcer-header-inner">
            <div class="enforcer-brand">
                <img src="{{ $__posoSeal }}" alt="Municipality of Luna seal" width="42" height="42">
                <div><strong>POSO Enforcer</strong><small>Municipality of Luna, Apayao</small></div>
            </div>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="enforcer-signout" type="submit"><i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i> Sign out</button></form>
        </div>
    </header>
    <main id="enforcer-content" class="enforcer-content" tabindex="-1">
        <nav class="d-flex gap-3 mb-4" aria-label="Enforcer navigation"><a href="{{ route('enforcer.create') }}">Record violation</a><a href="{{ route('enforcer.index') }}">My submissions</a></nav>
        @unless(request()->routeIs('enforcer.index'))@include('violations._enforcer_steps')@endunless
        <div class="enforcer-heading">
            <p class="enforcer-account"><i class="bi bi-person-badge me-1" aria-hidden="true"></i>{{ auth()->user()->name }} &middot; Enforcer</p>
            <h1>@yield('title', 'Record Violator')</h1>
        </div>
        @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif
        @if($errors->any())
            <div class="alert alert-danger" role="alert"><strong>Please check the following:</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @yield('content')
    </main>
    <footer class="enforcer-footer">Public Order &amp; Safety Office &middot; Enforcer Portal</footer>
    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
    @stack('scripts')
</body>
</html>
