@extends('layouts.enforcer')
@section('title', 'Submitted Record')
@section('content')
<div class="card p-4">
    <div class="submission-success"><i class="bi bi-check-lg" aria-hidden="true"></i><h2>Record submitted</h2><p>Your violation has been recorded.</p></div>
    <p class="text-muted small mb-0">Reference #{{ $violation->id }} &middot; {{ $violation->violation_date->format('M j, Y') }}</p>
    <dl class="submission-details">
        <dt>TOP (ticket number)</dt><dd>{{ $violation->citation?->ticket_no ?? 'Not recorded' }}</dd>
        <dt>Violator</dt><dd>{{ $violation->personDetail('full_name') }}</dd>
        <dt>Offense</dt><dd>{{ $violation->violationType->offense_name }}</dd>
        @foreach(['license_no' => 'License number', 'vehicle_plate' => 'Vehicle plate', 'vehicle_type' => 'Vehicle type', 'address' => 'Address', 'contact_no' => 'Contact number'] as $field => $label)
            @if(filled($violation->personDetail($field)))<dt>{{ $label }}</dt><dd>{{ $violation->personDetail($field) }}</dd>@endif
        @endforeach
        <dt>ID confiscated</dt><dd>{{ $violation->confiscated_id ?? 'Not recorded' }}</dd>
        <dt>Additional information</dt><dd class="text-break">{{ $violation->remarks ?? 'Not provided' }}</dd>
        @if($violation->citation)
            
            <dt>Fine</dt><dd>{{ $violation->citation->fine_label }}</dd>
            <dt>Due date</dt><dd>{{ $violation->citation->due_date->format('M j, Y') }}</dd>
            <dt>Settlement status</dt><dd><span class="badge badge-{{ $violation->citation->effective_payment_status }}">{{ $violation->payment_label }}</span></dd>
        @endif
    </dl>
    <a href="{{ route('enforcer.create') }}" class="btn btn-primary w-100 mt-4"><i class="bi bi-plus-lg me-2"></i>Record another violation</a>
</div>
@if($violation->snapshot_source === 'legacy')<p class="text-muted small mt-3">Historical profile details were preserved from the available data; original incident details may be incomplete.</p>@endif
@include('violations._minor_photos')
@endsection
