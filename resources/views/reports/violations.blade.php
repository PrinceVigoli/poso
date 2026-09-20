@extends('layouts.app')
@section('title', 'Violations Report')

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm" style="width:32px;height:32px;padding:0;display:flex;align-items:center;justify-content:center">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h5 class="mb-0 fw-semibold" style="font-size:15px">Violations report</h5>
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm ms-auto">
        <i class="bi bi-printer me-1"></i> Print
    </button>
</div>

{{-- Date filter --}}
<div class="card p-3 mb-3">
    <form method="GET" class="d-flex gap-2 align-items-end flex-wrap">
        <div>
            <label class="form-label mb-1" style="font-size:11px">From</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}">
        </div>
        <div>
            <label class="form-label mb-1" style="font-size:11px">To</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}">
        </div>
        <div>
            <label class="form-label mb-1" style="font-size:11px">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="pending"   {{ request('status')=='pending'  ?'selected':'' }}>Pending</option>
                <option value="settled"   {{ request('status')=='settled'  ?'selected':'' }}>Settled</option>
                <option value="dismissed" {{ request('status')=='dismissed'?'selected':'' }}>Dismissed</option>
            </select>
        </div>
        <button class="btn btn-primary btn-sm">Apply</button>
    </form>
</div>

{{-- Summary pills --}}
<div class="d-flex gap-2 mb-3 flex-wrap">
    <div class="card px-3 py-2" style="font-size:12px">
        <span style="color:#55637A">Total violations</span>
        <span class="fw-semibold ms-2" style="color:#11253F">{{ $total }}</span>
    </div>
    <div class="card px-3 py-2" style="font-size:12px">
        <span style="color:#55637A">Total fines</span>
        <span class="fw-semibold ms-2" style="color:#11253F">₱{{ number_format($totalFines, 2) }}</span>
    </div>
    <div class="card px-3 py-2" style="font-size:12px">
        <span style="color:#55637A">Period</span>
        <span class="fw-semibold ms-2" style="color:#11253F">
            {{ \Carbon\Carbon::parse($dateFrom)->format('M d') }} — {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }}
        </span>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0" style="font-size:12px">
            <thead>
                <tr>
                    
                    <th>Violator</th>
                    <th>Plate</th>
                    <th>Offense</th>
                    <th>Date</th>
                    <th>Location</th>
                    <th>Officer</th>
                    <th>Fine</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($violations as $v)
                <tr>
                    
                    <td style="font-weight:500;color:#111111">{{ $v->personDetail('full_name') }}</td>
                    <td>{{ $v->personDetail('vehicle_plate') ?? '—' }}</td>
                    <td>{{ $v->violationType->offense_name }}</td>
                    <td style="white-space:nowrap">{{ $v->violation_date->format('M d, Y') }}</td>
                    <td>{{ $v->location ?? '—' }}</td>
                    <td>{{ $v->officer->name }}</td>
                    <td style="font-weight:500">{{ $v->citation ? $v->citation->fine_label : '—' }}</td>
                    <td><span class="badge badge-{{ $v->status }}">{{ $v->payment_label }}</span></td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-4" style="color:#55637A">No violations in this period.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('styles')
<style>
@media print {
    #sidebar, .topbar, form, .btn, .breadcrumb { display: none !important; }
    #main { margin-left: 0 !important; }
    .content-area { padding: 0 !important; }
    .card { border: 1px solid #ccc !important; }
}
</style>
@endpush
