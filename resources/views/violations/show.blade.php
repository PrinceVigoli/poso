@extends('layouts.app')
@section('title', 'Violation #' . $violation->id)

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ auth()->user()->isEnforcer() ? route('enforcer.create') : route('violations.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i><span class="visually-hidden">Back</span></a>
    <h5 class="mb-0">Violation Record #{{ $violation->id }}</h5>
    <span class="badge badge-{{ $violation->status }}">{{ $violation->payment_label }}</span>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card stat-card p-3 h-100">
            <h6 class="mb-3 text-muted" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em">Violation Details</h6>
            <table class="table table-sm table-borderless mb-0" style="font-size:13px">
                <tr><td class="text-muted">TOP (ticket number)</td><td>{{ $violation->citation?->ticket_no ?? 'Not recorded' }}</td></tr>
                <tr><td class="text-muted" width="40%">Offense</td><td><strong>{{ $violation->violationType->offense_name }}</strong></td></tr>
                <tr><td class="text-muted">Date</td><td>{{ $violation->violation_date->format('F d, Y') }}</td></tr>
                <tr><td class="text-muted">Apprehension location</td><td>{{ $violation->location ?? 'Not recorded' }}</td></tr>
                <tr><td class="text-muted">Recorded by</td><td>{{ $violation->officer->name }}</td></tr>
                <tr><td class="text-muted">ID confiscated</td><td>{{ $violation->confiscated_id ?? 'Not recorded' }}</td></tr>
                <tr><td class="text-muted">Remarks</td><td>{{ $violation->remarks ?? '—' }}</td></tr>
            </table>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card stat-card p-3 mb-3">
            <h6 class="mb-3 text-muted" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em">Violator</h6>
            <div class="d-flex align-items-center gap-3">
                <div style="width:44px;height:44px;border-radius:50%;background:rgba(15,61,115,.12);display:flex;align-items:center;justify-content:center;font-size:20px;color:#0F3D73;flex-shrink:0">
                    <i class="bi bi-person"></i>
                </div>
                <div style="font-size:13px">
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('violators.show', $violation->violator) }}" class="fw-semibold text-decoration-none">
                            {{ $violation->personDetail('full_name') }}
                        </a>
                    @else
                        <span class="fw-semibold">{{ $violation->personDetail('full_name') }}</span>
                    @endif
                    <div class="text-muted">{{ $violation->personDetail('vehicle_plate') ?? 'No plate' }} · {{ $violation->personDetail('vehicle_type') ?? '—' }}</div>
                    <div class="text-muted">{{ $violation->personDetail('license_no') ?? 'No license no.' }}</div>
                    <div class="mt-2 text-break"><strong>Address recorded for this incident:</strong><br>{{ $violation->personDetail('address') ?: 'Not recorded' }}</div>
                </div>
            </div>
        </div>
        @if($violation->citation)
        <div class="card stat-card p-3">
            <h6 class="mb-3 text-muted" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em">Treasury payment verification</h6>
            <table class="table table-sm table-borderless mb-0" style="font-size:13px">
                
                <tr><td class="text-muted">Fine Amount</td><td><strong>{{ $violation->citation->fine_label }}</strong></td></tr>
                <tr><td class="text-muted">Due Date</td><td>{{ $violation->citation->due_date->format('F d, Y') }}</td></tr>
                <tr>
                    <td class="text-muted">Payment</td>
                    <td><span class="badge badge-{{ $violation->citation->effective_payment_status }}">{{ $violation->payment_label }}</span></td>
                </tr>
                @if($violation->citation->paid_at)
                <tr><td class="text-muted">Settlement date (POSO verification)</td><td>{{ $violation->citation->paid_at ? $violation->citation->paid_at->format('F d, Y') : 'N/A' }}</td></tr>
                @endif
            </table>
            @if($violation->status !== 'dismissed' && $violation->citation->payment_status !== 'paid' && $violation->citation->fine_amount !== null && auth()->user()->isAdmin())
            <form method="POST" action="{{ route('violations.verify-payment', $violation) }}" data-payment-ticket="Violation #{{ $violation->id }}" data-payment-person="{{ $violation->personDetail('full_name') }}" data-payment-amount="{{ $violation->citation->fine_label }}" data-payment-offense="{{ $violation->violationType->offense_name }}" data-payment-address="{{ $violation->personDetail('address') }}" data-violation-id="{{ $violation->id }}" class="mt-2">
                @csrf
                <button class="btn btn-sm btn-success w-100">
                    <i class="bi bi-check-circle me-1"></i> Verify Treasury receipt
                </button>
            </form>
            @endif
            @if($violation->citation->treasury_receipt_no)
                <p class="small mt-3 mb-1">Treasury receipt: <strong>{{ $violation->citation->treasury_receipt_no }}</strong></p>
                <p class="small mb-1">Treasury receipt date: {{ $violation->citation->receipt_date?->format('M j, Y') ?? 'Not recorded for this legacy receipt' }}</p>
                <p class="small mb-1">Treasury receipt total: {{ $violation->citation->treasuryReceipt?->total_amount === null ? 'Not recorded' : '₱'.number_format($violation->citation->treasuryReceipt->total_amount, 2) }}</p>
                <p class="text-muted small">Verified by POSO on {{ $violation->citation->verified_at?->format('M j, Y g:i A') }}</p>
            @endif
            <p class="text-muted small mt-3 mb-0">Payment and official receipts are handled by the Treasury office. POSO verifies the Treasury receipt.</p>
            
        </div>
        @endif
    </div>
</div>

<div class="d-flex gap-2 mt-3">
    
</div>
@if($violation->citation?->payment_status === 'paid' && auth()->user()->isAdmin())
<details class="card p-3 mt-3">
    <summary class="fw-semibold">Correct a mistaken payment verification</summary>
    <p class="small mt-3">Reverse the incorrect verification first, then enter the correct receipt. The original verification and your reason remain in the history.</p>
    <form method="POST" action="{{ route('violations.reverse-payment', $violation) }}">
        @csrf
        <input type="hidden" name="verification_version" value="{{ $violation->citation->verification_version }}">
        <label for="reversalReason" class="form-label">Reason for correction</label>
        <textarea class="form-control mb-3" id="reversalReason" name="reason" minlength="10" maxlength="1000" required></textarea>
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="reverseConfirmed" name="reverse_confirmed" value="1" required><label class="form-check-label" for="reverseConfirmed">I checked the record and confirm this verification was incorrect.</label></div>
        <button class="btn btn-outline-danger" type="submit">Reverse verification</button>
    </form>
</details>
@endif
@if($violation->paymentEvents->isNotEmpty())
<section class="card p-3 mt-3"><h3 class="h6">Payment verification history</h3>
    @foreach($violation->paymentEvents->sortByDesc('id') as $event)
        <div class="border-top py-2 small"><strong>{{ $event->action === 'fine_configured' ? 'Fine configured' : ucfirst($event->action) }}</strong> by {{ $event->user?->name ?? 'Former user' }} on {{ $event->created_at->format('M j, Y g:i A') }}
        @if($event->action === 'fine_configured')<div>Fine: {{ $event->before_state['fine_amount'] ?? 'Not configured' }} → ₱{{ number_format($event->after_state['fine_amount'], 2) }}</div>@endif
        <div>Treasury receipt: {{ $event->after_state['treasury_receipt_no'] ?? $event->before_state['treasury_receipt_no'] ?? 'Not recorded' }}</div>
        @if($event->reason)<p class="mb-0">Reason: {{ $event->reason }}</p>@endif</div>
    @endforeach
</section>
@endif
@include('violations._reconcile_fine')
@if($violation->snapshot_source === 'legacy')<p class="text-muted small mt-3">Historical profile details were preserved from the available data; original incident details may be incomplete.</p>@endif
@include('violations._minor_photos')
@endsection
