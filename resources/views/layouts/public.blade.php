<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>POSO Luna &mdash; @yield('title', 'Citizen Portal')</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.css') }}">
    <link href="{{ asset('vendor/fonts/fonts.css') }}" rel="stylesheet">
    @foreach(['portal-base', 'portal', 'public'] as $sheet)<link rel="stylesheet" href="{{ asset('css/'.$sheet.'.css') }}?v={{ filemtime(public_path('css/'.$sheet.'.css')) }}">@endforeach
    @stack('styles')
</head>
<body class="public-page {{ request()->routeIs('login', 'login.submit') ? 'login-page' : '' }} {{ request()->routeIs('home') ? 'landing-page' : '' }}">
@php
    $isLanding = request()->routeIs('home');
    $isLogin = request()->routeIs('login', 'login.submit');
    $searchRoute = request()->routeIs('citizen.*') ? 'citizen.search' : 'search';
@endphp
<a class="skip-link" href="#public-content">Skip to content</a>
<header class="public-header"><div class="public-header-inner">
    <a href="{{ route($isLanding || $isLogin ? 'home' : $searchRoute) }}" class="public-brand"><img src="{{ $__posoSeal }}" alt="Municipality of Luna seal"><span><strong>{{ $isLanding || $isLogin ? 'POSO' : 'POSO Citizen Portal' }}</strong><small>Luna, Apayao</small></span></a>
    @if($isLanding)
    <nav class="landing-nav" aria-label="Main navigation"><a href="#about-poso">About POSO</a><a href="#ordinances">Ordinances</a><a href="#staff-directory">Staff directory</a></nav>
    @elseif($isLogin)<a href="{{ route('citizen.search') }}" class="header-link">Check Settlement Status</a>@endif
</div></header>
<main id="public-content" class="public-content" tabindex="-1">@yield('content')</main>
<footer class="public-footer">Public Order &amp; Safety Office &middot; Municipality of Luna</footer>
@stack('scripts')
</body>
</html>
