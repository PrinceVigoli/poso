@extends('layouts.public')
@section('title', 'Check Settlement Status')
@section('content')
<section class="citizen-hero"><h1>Check Settlement Status</h1><p>Search by the full name, driver's licence number or plate number on the violation record.</p></section>
<section class="card citizen-search">
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route(request()->routeIs('citizen.*') ? 'citizen.lookup' : 'search.lookup') }}">
        @csrf
        <label class="form-label" for="query">Full name, licence number or plate number</label>
        <input class="form-control mb-3" id="query" name="query" maxlength="255" value="{{ old('query') }}" required autocomplete="off" spellcheck="false">
        <button class="btn btn-primary" type="submit">Check Settlement Status</button>
    </form>
    <p class="text-muted small mt-3 mb-0">Enter it exactly as written on your citation ticket. A licence or plate number narrows the search, so use one if you have it. Results stay open for 15 minutes. Because people can share a name, a name search may return records belonging to someone else — check the apprehension date against your own ticket.</p>
</section>
@endsection
