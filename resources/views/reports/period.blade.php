@extends('layouts.app')
@section('title', ucfirst($period).' Report')
@section('content')
<div class="card p-3 mb-4 report-controls"><form method="GET" action="{{ route('reports.period') }}" class="d-flex flex-wrap gap-3 align-items-end">
<div><label class="form-label" for="period">Report period</label><select name="period" id="period" class="form-select">@foreach(['daily','weekly','monthly'] as $option)<option value="{{ $option }}" @selected($period === $option)>{{ ucfirst($option) }}</option>@endforeach</select></div>
<div><label class="form-label" for="date">Date within the period</label><input type="date" class="form-control" name="date" id="date" value="{{ $date }}" required></div>
<button class="btn btn-primary">View report</button><button type="button" onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer me-2"></i>Print report</button></form></div>
<p class="report-controls"><a class="btn btn-outline-primary" href="{{ route('reports.period', ['period' => $period, 'date' => $date, 'download' => 'csv']) }}">Download complete period (CSV)</a></p>
<p class="small text-muted">Each section shows up to 50 rows per page. Printing includes the displayed rows. Download the CSV for all rows in this period.</p>
<section class="card p-4 report-document">
<header class="mb-4"><h2 class="h5">Public Order &amp; Safety Office &middot; Luna, Apayao</h2><p class="mb-1">{{ ucfirst($period) }} apprehension and settlement report</p><strong>{{ $dateFrom }} to {{ $dateTo }}</strong><p class="small text-muted mt-2">POSO verifies Treasury receipts. No money is collected by POSO.</p></header>
<div class="row g-3 mb-4"><div class="col-sm-6"><strong>{{ $records->total() }}</strong> apprehensions in this period</div><div class="col-sm-6"><strong>{{ $settlements->total() }}</strong> settlements verified in this period</div></div>
<h3 class="card-ttl mb-3">Apprehensions</h3>
<div class="table-responsive"><table class="table"><thead><tr><th>Full name</th><th>Address</th><th>Violation</th><th>Apprehension date</th><th>Status</th><th>Settlement date</th></tr></thead><tbody>
@forelse($records as $v)<tr><td>{{ $v->personDetail('full_name') ?? 'Deleted profile' }}</td><td>{{ $v->personDetail('address') ?: 'Not recorded' }}</td><td>{{ $v->violationType?->offense_name }}</td><td>{{ $v->violation_date->format('M j, Y') }}</td><td>{{ $v->payment_label }}</td><td>{{ $v->payment_label === 'Settled' ? ($v->citation->paid_at?->format('M j, Y') ?? 'Not recorded') : '-' }}</td></tr>@empty<tr><td colspan="6">No apprehensions in this period.</td></tr>@endforelse
</tbody></table></div>
<div class="report-controls">{{ $records->links() }}</div>
<h3 class="card-ttl my-3">Settlements verified by POSO</h3>
<div class="table-responsive"><table class="table"><thead><tr><th>Full name</th><th>Violation</th><th>Apprehension date</th><th>Settlement date</th><th>Treasury receipt</th><th>Treasury date</th></tr></thead><tbody>
@forelse($settlements as $v)<tr><td>{{ $v->personDetail('full_name') ?? 'Deleted profile' }}</td><td>{{ $v->violationType?->offense_name }}</td><td>{{ $v->violation_date->format('M j, Y') }}</td><td>{{ $v->citation->paid_at->format('M j, Y') }}</td><td>{{ $v->citation->treasury_receipt_no ?: 'Legacy record - not recorded' }}</td><td>{{ $v->citation->receipt_date?->format('M j, Y') ?? 'Not recorded' }}</td></tr>@empty<tr><td colspan="6">No settlements in this period.</td></tr>@endforelse
</tbody></table></div>
<div class="report-controls">{{ $settlements->links() }}</div>
<p class="text-muted small mt-3">Prepared by {{ auth()->user()->name }} &middot; {{ now()->format('M j, Y g:i A') }}</p>
@if($paymentEvents->isNotEmpty())
<h3 class="card-ttl my-3">Verification changes in this period</h3>
<div class="table-responsive"><table class="table"><thead><tr><th>Record</th><th>Action</th><th>Date</th><th>Admin</th><th>Reason</th></tr></thead><tbody>
@foreach($paymentEvents as $event)<tr><td>#{{ $event->violation_id }}</td><td>{{ ucfirst($event->action) }}</td><td>{{ $event->created_at->format('M j, Y g:i A') }}</td><td>{{ $event->user?->name ?? 'Former user' }}</td><td>{{ $event->reason ?? '-' }}</td></tr>@endforeach
</tbody></table></div>
<div class="report-controls">{{ $paymentEvents->links() }}</div>
@endif
<p class="text-muted small">Settlement status reflects the current record. Verification changes preserve corrections separately.</p>
</section>
@endsection
@push('styles')<style>@media print { .report-controls, .page-heading { display:none !important; } .report-document { border:0; padding:0 !important; break-inside:auto; } .report-document .table { font-size:10px; } .report-document thead { display:table-header-group; } .report-document tr { break-inside:avoid; } }</style>@endpush
