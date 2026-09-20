@extends('layouts.enforcer')
@section('title', 'Already Cited Today')

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('enforcer.create') }}" class="btn btn-outline-secondary btn-sm" style="width:32px;height:32px;padding:0;display:flex;align-items:center;justify-content:center">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h5 class="mb-0 fw-semibold" style="font-size:15px">Already cited today</h5>
</div>

<div class="card p-4" style="max-width:520px">
    <div class="alert alert-warning d-flex gap-2" style="font-size:12.5px">
        <i class="bi bi-exclamation-triangle"></i>
        <div>
            <strong>{{ $violator->full_name }}</strong> already has {{ $todaysViolations->count() }}
            violation{{ $todaysViolations->count() === 1 ? '' : 's' }} on record for today.
        </div>
    </div>

    <div class="mb-3">
        @foreach($todaysViolations as $v)
            <div class="d-flex align-items-center justify-content-between" style="padding:9px 0;border-bottom:1px solid #EAEEF2;font-size:12.5px">
                <div>
                    <div style="font-weight:600;color:#11253F">{{ $v->violationType->offense_name }}</div>
                    <div style="color:#93A0B3;font-size:11px">{{ $v->violation_date->format('M j, Y') }} </div>
                </div>
                <span class="badge badge-{{ $v->status }}">{{ $v->payment_label }}</span>
            </div>
        @endforeach
    </div>

    <div class="mb-3" style="font-size:12.5px;color:#55637A">
        You're about to cite them again for: <strong style="color:#11253F">{{ $newOffense->offense_name }}</strong>
    </div>

    <form method="POST" action="{{ route('enforcer.preview') }}">
        @csrf
        @include('violations._enforcer_details_hidden')
        <input type="hidden" name="full_name" value="{{ $violator->full_name }}">
        <input type="hidden" name="violation_type_id" value="{{ $violationTypeId }}">
        <input type="hidden" name="matched_violator_id" value="{{ $violator->id }}">
        <input type="hidden" name="confirm_duplicate" value="1">
        <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-check me-1"></i> Continue to preview
        </button>
    </form>
    <a href="{{ route('violations.create') }}" class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
</div>
<form method="POST" action="{{ route('enforcer.edit') }}" class="mt-3">@csrf<button class="btn btn-outline-secondary">Back to edit details</button></form>
@endsection
