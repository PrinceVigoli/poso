@extends('layouts.public')
@section('title', 'Settlement Status')
@section('content')
<h1 class="h3 fw-semibold mb-2">Settlement status</h1>
<section class="card p-4 mb-3">
    <dl class="row mb-0">
        <dt class="col-sm-5">TOP (ticket number)</dt><dd class="col-sm-7">{{ $record->citation->ticket_no }}</dd>
        <dt class="col-sm-5">Status</dt><dd class="col-sm-7">{{ $record->payment_label }}</dd>
    </dl>
    @if($record->payment_label === 'Not Yet Verified')
        <p class="alert alert-info mt-3 mb-0">POSO has not verified a Treasury payment for this ticket. If you have already paid, present your Treasury receipt to POSO for verification.</p>
    @endif
</section>
<form method="POST" class="mt-3" action="{{ route(request()->routeIs('citizen.*') ? 'citizen.forget' : 'search.forget') }}">@csrf<button class="btn btn-outline-secondary">Close result</button></form>
<p class="citizen-help">This access opens one ticket for 15 minutes. Payments and official receipts are handled by Treasury.</p>
@endsection
