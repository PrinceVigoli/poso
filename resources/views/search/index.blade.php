@extends('layouts.public')
@section('title', 'Check Settlement Status')
@section('content')
<section class="citizen-hero"><h1>Check Settlement Status</h1><p>Use the TOP number and private access code issued with your ticket.</p></section>
<section class="card citizen-search">
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route(request()->routeIs('citizen.*') ? 'citizen.lookup' : 'search.lookup') }}" autocomplete="off">
        @csrf
        <label class="form-label" for="ticket_no">TOP (ticket number)</label>
        <input class="form-control mb-3" id="ticket_no" name="ticket_no" maxlength="100" required autocomplete="off" spellcheck="false" placeholder="Enter the TOP number on your ticket">
        <label class="form-label" for="access_code">Private access code</label>
        <input type="password" class="form-control mb-3" id="access_code" name="access_code" maxlength="64" required autocomplete="off" spellcheck="false" aria-describedby="code-help">
        <p class="small text-muted" id="code-help">Keep this code private. It opens only this ticket's settlement status. If you lost it, contact POSO for identity verification and a replacement.</p>
        <button class="btn btn-primary" type="submit">Check Settlement Status</button>
    </form>
    <p class="text-muted small mt-3 mb-0">Access closes after 15 minutes. Close your result when finished, especially on a shared device. Names, license numbers and plates cannot be used to search for records.</p>
    <p class="small mt-3 mb-0"><a href="{{ route(request()->routeIs('citizen.*') ? 'citizen.privacy' : 'search.privacy') }}">Privacy notice</a></p>
</section>
@endsection
