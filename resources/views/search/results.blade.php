@extends('layouts.public')
@section('title', 'Settlement Status')
@section('content')
<h1 class="h3 fw-semibold mb-2">Settlement status</h1>
<p class="text-muted">Records recorded under <strong>{{ $entered }}</strong>.</p>

@if($records->isEmpty())
    <div class="alert alert-secondary" role="status">
        No violation record was found under that name. Check the spelling against your citation ticket, or contact POSO for assistance.
    </div>
@else
    @if($records->count() > 1)
        <div class="alert alert-warning" role="status">
            {{ $records->count() }} records were recorded under this name. People can share a name, so these may not all be yours — match the apprehension date against your own citation ticket.
        </div>
    @endif
    @if($limited)
        <div class="alert alert-secondary" role="status">Only the most recent records are shown. Contact POSO for a full history.</div>
    @endif

    @foreach($records as $record)
        <section class="card p-4 mb-3">
            <dl class="row mb-0">
                <dt class="col-sm-5">Status</dt>
                <dd class="col-sm-7">{{ $record->payment_label }}</dd>

                <dt class="col-sm-5">Date of apprehension</dt>
                <dd class="col-sm-7">{{ $record->violation_date->format('M j, Y') }}</dd>

                <dt class="col-sm-5">Offense</dt>
                <dd class="col-sm-7">{{ $record->violationType?->offense_name ?? 'Not recorded' }}</dd>

                @if($record->payment_label === 'Settled')
                    <dt class="col-sm-5">POSO verification date</dt>
                    <dd class="col-sm-7">{{ $record->citation->paid_at?->format('M j, Y') ?? 'Not recorded' }}</dd>
                @endif
            </dl>
            @if($record->payment_label === 'Not Yet Verified')
                <p class="alert alert-info mt-3 mb-0">POSO has not verified a Treasury payment for this record. If you have already paid, present your Treasury receipt to POSO for verification.</p>
            @endif
        </section>
    @endforeach
@endif

<form method="POST" class="mt-3" action="{{ route(request()->routeIs('citizen.*') ? 'citizen.forget' : 'search.forget') }}">@csrf<button class="btn btn-outline-secondary">Close records</button></form>
<p class="citizen-help">Payments and official receipts are handled by Treasury. Your settlement status updates after POSO verifies your receipt.</p>
@endsection
