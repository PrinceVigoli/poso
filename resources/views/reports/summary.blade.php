@extends('layouts.app')
@section('title', 'Summary Report')

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm" style="width:32px;height:32px;padding:0;display:flex;align-items:center;justify-content:center">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h5 class="mb-0 fw-semibold" style="font-size:15px">Summary report</h5>
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm ms-auto">
        <i class="bi bi-printer me-1"></i> Print
    </button>
</div>

<div class="card p-3 mb-3">
    <form method="GET" class="d-flex gap-2 align-items-end">
        <div>
            <label class="form-label mb-1" style="font-size:11px">From</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}">
        </div>
        <div>
            <label class="form-label mb-1" style="font-size:11px">To</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}">
        </div>
        <button class="btn btn-primary btn-sm">Apply</button>
    </form>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="stat-card text-center"><div class="stat-num">{{ $byType->sum('count') }}</div><div class="stat-lbl">Total violations</div></div></div>
    <div class="col-md-3"><div class="stat-card text-center"><div class="stat-num">{{ $byStatus->get('pending', 0) }}</div><div class="stat-lbl">Pending</div></div></div>
    <div class="col-md-3"><div class="stat-card text-center"><div class="stat-num">{{ $byStatus->get('settled', 0) }}</div><div class="stat-lbl">Settled</div></div></div>
    <div class="col-md-3"><div class="stat-card text-center"><div class="stat-num">₱{{ number_format($totalCollected, 0) }}</div><div class="stat-lbl">Collected</div></div></div>
</div>

<div class="row g-3">
    <div class="col-md-7">
        <div class="card p-3">
            <div class="fw-semibold mb-3" style="font-size:13px">Violations by offense type</div>
            <table class="table table-sm mb-0" style="font-size:12px">
                <thead><tr><th>Offense</th><th class="text-end">Count</th></tr></thead>
                <tbody>
                    @forelse($byType->where('count','>',0) as $t)
                    <tr>
                        <td>{{ $t->offense_name }}</td>
                        <td class="text-end fw-semibold">{{ $t->count }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="text-center py-3" style="color:#55637A">No data for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card p-3 h-100">
            <div class="fw-semibold mb-3" style="font-size:13px">Daily breakdown</div>
            <table class="table table-sm mb-0" style="font-size:12px">
                <thead><tr><th>Date</th><th class="text-end">Count</th></tr></thead>
                <tbody>
                    @forelse($dailyCounts as $d)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($d->date)->format('M d, Y') }}</td>
                        <td class="text-end fw-semibold">{{ $d->count }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="text-center py-3" style="color:#55637A">No data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
