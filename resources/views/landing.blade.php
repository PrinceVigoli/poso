@extends('layouts.public')
@section('title', 'Public Order & Safety Office')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}">
@endpush
@section('content')
<section class="landing-hero" aria-labelledby="landing-title">
    <img class="landing-hero-photo" src="{{ asset('images/luna-municipal-hall.webp') }}" alt="Luna Municipal Hall in Luna, Apayao" fetchpriority="high" width="1389" height="1024">
    <div class="landing-intro">
        <p class="landing-eyebrow"><span></span> MUNICIPALITY OF LUNA, APAYAO</p>
        <h1 id="landing-title">A safer community.<br>A more orderly Luna.</h1>
        <p class="landing-lead">Public Order &amp; Safety Office</p>
        <p class="landing-description">The Public Order &amp; Safety Office serves the whole Municipality of Luna, Apayao, with a focus on public order, community safety, and the well-being of the people who live and work here.</p>
        <div class="landing-actions"><a class="btn btn-primary" href="#about-poso">About POSO @include('partials.landing-icon', ['icon' => 'arrow-right'])</a><a class="landing-secondary" href="#citizen-services">Explore citizen services</a></div>
    </div>
    <a class="landing-photo-credit" href="https://lunaapayao.gov.ph/" target="_blank" rel="noopener noreferrer">Luna Municipal Hall &middot; Photo: Municipality of Luna</a>
</section>
<section id="about-poso" class="landing-steps landing-about" aria-labelledby="about-title">
    <div class="landing-section-heading"><div><p class="landing-eyebrow">ABOUT THE OFFICE</p><h2 id="about-title">Public service for the whole municipality</h2></div></div>
    <ul class="steps-grid">
        <li><span class="step-number">PUBLIC ORDER</span><h3>An orderly community</h3><p>Public order helps people live, work, and share public spaces with respect for one another.</p></li>
        <li><span class="step-number">PUBLIC SAFETY</span><h3>Safety in everyday life</h3><p>Community safety concerns the well-being of residents and everyone who visits Luna.</p></li>
        <li><span class="step-number">MUNICIPALITY OF LUNA</span><h3>A municipality-wide focus</h3><p>POSO's role extends across Luna, Apayao. Violation record management is one part of its work.</p></li>
    </ul>
</section>
@include('partials.ordinances')
@include('partials.staff-directory')
<section id="citizen-services" class="landing-steps" aria-labelledby="citizen-services-title">
    <div class="landing-section-heading"><div><p class="landing-eyebrow">AVAILABLE ONLINE SERVICE</p><h2 id="citizen-services-title">Violation record search</h2></div></div>
    <p class="landing-description">Use the Citizen Portal to check your recorded apprehensions, paid or unpaid status, and settlement dates. Search by name, license number, or vehicle plate.</p>
    <div class="landing-actions"><a class="btn btn-primary" href="{{ route('citizen.search') }}">Check my record @include('partials.landing-icon', ['icon' => 'arrow-up-right'])</a></div>
    <p class="landing-no-account">@include('partials.landing-icon', ['icon' => 'check-circle']) No citizen account or sign in required</p>
</section>
<section id="how-it-works" class="landing-steps" aria-labelledby="steps-title">
    <div class="landing-section-heading"><div><p class="landing-eyebrow">FOR VIOLATION RECORDS</p><h2 id="steps-title">How to settle a recorded violation</h2></div></div>
    <ol class="steps-grid">
        <li><span class="step-number">01</span><h3>Check your record</h3><p>Open the Citizen Portal to view your apprehension dates and current settlement status.</p></li>
        <li><span class="step-number">02</span><h3>Pay at the Treasury</h3><p>Settle your violation at the Treasury office and keep the receipt for verification.</p></li>
        <li><span class="step-number">03</span><h3>Present your receipt</h3><p>Bring the receipt to POSO. An administrator verifies it and marks the record as paid.</p></li>
    </ol>
    <p class="landing-settlement-note">@include('partials.landing-icon', ['icon' => 'info-circle']) POSO does not collect payments. Your settlement date is the date POSO verifies your Treasury receipt. Past records remain available for reference.</p>
</section>
<section class="landing-support"><div><h2>Connect with POSO</h2><p>For questions about the office's services or a public order and safety concern, visit the Public Order &amp; Safety Office in Luna, Apayao.</p></div><span class="support-seal"><img src="{{ $__posoSeal }}" alt="Municipality of Luna seal"></span></section>
@endsection
