@extends('layouts.enforcer')
@section('title', 'Confirm Violator')

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('enforcer.create') }}" class="btn btn-outline-secondary btn-sm" style="width:32px;height:32px;padding:0;display:flex;align-items:center;justify-content:center">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h5 class="mb-0 fw-semibold" style="font-size:15px">Confirm violator</h5>
</div>

<div class="card p-4" style="max-width:520px">
    <div class="alert alert-warning d-flex gap-2" style="font-size:12.5px">
        <i class="bi bi-exclamation-triangle"></i>
        <div>Possible profiles for "<strong>{{ $full_name }}</strong>". Check the address and confirm whether this is the same person. A shared name alone is not enough.</div>
    </div>

    @foreach($candidates as $c)
        <form method="POST" action="{{ route('enforcer.preview') }}" class="mb-2">
            @csrf
        @include('violations._enforcer_details_hidden')
            <input type="hidden" name="full_name" value="{{ $full_name }}">
            <input type="hidden" name="violation_type_id" value="{{ $violationTypeId }}">
            <input type="hidden" name="matched_violator_id" value="{{ $c->id }}">
            <button type="submit" class="btn btn-outline-secondary w-100 d-flex align-items-center gap-2 text-start" style="padding:10px 14px">
                <div style="width:32px;height:32px;border-radius:50%;background:#E9F0F9;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#0F3D73;flex-shrink:0">
                    {{ strtoupper(substr($c->display_name,0,1)) }}
                </div>
                <div class="flex-fill">
                    <div style="font-size:13px;font-weight:600;color:#11253F">{{ $c->display_name }}</div>
                    <div style="font-size:11px;color:#93A0B3">Profile #{{ $c->id }} · {{ $c->address ?? 'Address not recorded' }} · {{ $c->vehicle_type ?? '—' }} · {{ $c->vehicle_plate ?? 'No plate' }} · {{ $c->match_pct }}% match</div>
                </div>
            </button>
        </form>
    @endforeach

    <hr>

    <form method="POST" action="{{ route('enforcer.preview') }}">
        @csrf
        @include('violations._enforcer_details_hidden')
        <input type="hidden" name="full_name" value="{{ $full_name }}">
        <input type="hidden" name="violation_type_id" value="{{ $violationTypeId }}">
        <input type="hidden" name="confirm_new" value="1">
        <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-person-plus me-1"></i> None of these — add "{{ $full_name }}" as a new violator
        </button>
    </form>
</div>
<form method="POST" action="{{ route('enforcer.edit') }}" class="mt-3">@csrf<button class="btn btn-outline-secondary">Back to edit details</button></form>
@endsection
