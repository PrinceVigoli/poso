@extends('layouts.app')
@section('title', $violator->full_name)

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('violators.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i><span class="visually-hidden">Back</span></a>
    <h5 class="mb-0">Violator Profile</h5>
    @if($isArchived)
        <span class="badge bg-secondary">Archived — no outstanding violations</span>
    @endif
    @if($isRepeatOffender)
        <span class="badge badge-repeat ms-1">Repeat Offender</span>
    @endif
</div>

<div class="row g-3">
    @if($isArchived)
    <div class="col-12"><div class="profile-summary"><i class="bi bi-check-circle me-2"></i>No outstanding violations. History retained for future reference.</div></div>
    @endif
    {{-- Profile card --}}
    <div class="col-md-4">
        <div class="card stat-card p-3">
            <div class="text-center mb-3">
                <div style="width:64px;height:64px;border-radius:50%;background:rgba(15,61,115,.12);display:flex;align-items:center;justify-content:center;margin:0 auto;font-size:28px;color:#0F3D73">
                    <i class="bi bi-person"></i>
                </div>
                <h6 class="mt-2 mb-0">{{ $violator->full_name }}</h6>
                @if($isRepeatOffender)
                    {{-- Show the qualifying count and the total separately: they differ whenever a violation was dismissed. --}}
                    <small class="text-danger d-block">⚠ Repeat Offender ({{ $countedViolations }} counted {{ \Illuminate\Support\Str::plural('violation', $countedViolations) }})</small>
                @endif
                <small class="text-muted">{{ $violations->count() }} {{ \Illuminate\Support\Str::plural('violation', $violations->count()) }} on record</small>
            </div>
            <hr>
            <div style="font-size:13px">
                <div class="mb-2"><span class="text-muted">License No:</span><br><strong>{{ $violator->license_no ?? '—' }}</strong></div>
                <div class="mb-2"><span class="text-muted">Plate No:</span><br><strong>{{ $violator->vehicle_plate ?? '—' }}</strong></div>
                <div class="mb-2"><span class="text-muted">Vehicle:</span><br><strong>{{ $violator->vehicle_type ?? '—' }}</strong></div>
                <div class="mb-2"><span class="text-muted">Address:</span><br><strong>{{ $violator->address ?? '—' }}</strong></div>
                <div class="mb-2"><span class="text-muted">Contact:</span><br><strong>{{ $violator->contact_no ?? '—' }}</strong></div>
                <div><span class="text-muted">Birthdate:</span><br><strong>{{ $violator->birthdate ? $violator->birthdate->format('M d, Y') : '—' }}</strong></div>
            </div>
            <hr>
            <div class="d-flex gap-2">
                
                @if(auth()->user()->isEnforcer())<a href="{{ route('violations.create') }}?violator_id={{ $violator->id }}&full_name={{ urlencode($violator->full_name) }}" class="btn btn-sm btn-primary w-100">
                    <i class="bi bi-plus me-1"></i> Add Violation
                </a>@endif
            </div>
        </div>
    </div>

    {{-- Violation history --}}
    <div class="col-md-8">
        <div class="card stat-card p-3">
            <h6 class="mb-3">Violation History</h6>
            <p class="text-muted small">Includes settled violations for future reference. Total recorded: {{ $violations->count() }}.</p>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Offense</th>
                            <th>Date</th>
                            
                            <th>Fine</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($violations as $v)
                        <tr>
                            <td><small class="text-muted d-block">Record #{{ $v->id }}</small>{{ $v->violationType->offense_name }}</td>
                            <td>{{ $v->violation_date->format('M d, Y') }}</td>
                            
                            <td>{{ $v->citation?->fine_label ?? 'Not configured' }}</td>
                            <td><span class="badge badge-{{ $v->status }}">{{ $v->payment_label }}</span>
                                @if($v->citation?->payment_status === 'paid')<span class="badge bg-secondary">Archived (settled)</span>@endif
                            </td>
                            <td>
                                <a href="{{ route('violations.show', $v) }}" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:12px">View</a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No violations recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
