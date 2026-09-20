@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<div class="page-heading">
    <div><h1>Dashboard</h1><p>An overview of apprehensions and Treasury payment verification.</p></div>
    @if(auth()->user()->isEnforcer())<a href="{{ route('violations.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Record Violation</a>@endif
</div>
<div class="d-flex flex-wrap gap-2 mb-3"><a class="btn btn-primary" href="{{ route('violations.index', ['payment_status' => 'ready']) }}">Ready to verify ({{ $stats['ready_to_verify'] }})</a><a class="btn btn-outline-secondary" href="{{ route('violations.index', ['payment_status' => 'needs_review']) }}">Amount needs review ({{ $stats['needs_review'] }})</a></div>
@if($stats['overdue_citations'] > 0)
<div class="attention-banner" role="status">
    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
    <strong>{{ number_format($stats['overdue_citations']) }} overdue verification(s) need attention</strong>
    <a href="{{ route('violations.index', ['payment_status' => 'overdue']) }}">Review overdue verification <i class="bi bi-arrow-right ms-1"></i></a>
</div>
@endif
@php
    $cards = [
        ['label' => 'Total violations', 'value' => number_format($stats['total_violations']), 'note' => 'All recorded offenses', 'icon' => 'bi-clipboard2-data'],
        ['label' => 'Active violators', 'value' => number_format($stats['active_violators']), 'note' => 'With unverified settlements', 'icon' => 'bi-person'],
        ['label' => 'Archived violators', 'value' => number_format($stats['archived_violators']), 'note' => 'No outstanding violations', 'icon' => 'bi-archive'],
        ['label' => 'Settled records', 'value' => number_format($stats['paid_records']), 'note' => 'Treasury payment recorded', 'icon' => 'bi-clipboard-check'],
    ];
@endphp
<div class="row g-3 mb-4">
    @foreach($cards as $card)
    <div class="col-sm-6 col-xl-3"><div class="card metric-card">
        <div class="metric-label">{{ $card['label'] }}</div>
        <span class="metric-icon"><i class="bi {{ $card['icon'] }}" aria-hidden="true"></i></span>
        <div class="metric-value">{{ $card['value'] }}</div>
        <div class="metric-note">{{ $card['note'] }}</div>
    </div></div>
    @endforeach
</div>
<div class="row g-3 mb-4">
    <div class="col-xl-6">
        <section class="card h-100" aria-labelledby="weekly-heading">
            <div class="sec-hd border-0">
                <div><h2 class="card-ttl" id="weekly-heading">Violations this week</h2><div class="chart-subtitle">{{ today()->subDays(6)->format('M j') }} &ndash; {{ today()->format('M j, Y') }}</div></div>
                @if(auth()->user()->isAdmin())<a href="{{ route('reports.period') }}" class="text-decoration-none small">Reports <i class="bi bi-arrow-up-right"></i></a>@endif
            </div>
            <div class="px-4 pb-4">
                <div class="week-chart" role="img" aria-label="Daily violation counts: {{ $weekly->map(fn ($count, $date) => \Carbon\Carbon::parse($date)->format('M j').': '.$count)->implode('; ') }}">
                    @foreach($weekly as $date => $count)
                    <div class="chart-column" aria-hidden="true"><span class="chart-count">{{ $count }}</span><div class="chart-bar" style="height:{{ ($count / max(1, $weekly->max())) * 165 }}px"></div></div>
                    @endforeach
                </div>
                <div class="chart-labels" aria-hidden="true">@foreach($weekly as $date => $count)<span>{{ \Carbon\Carbon::parse($date)->format('D') }}</span>@endforeach</div>
                @if($weekly->sum() === 0)<p class="text-muted small text-center mt-3 mb-0">No violations recorded in the last seven days.</p>@endif
            </div>
        </section>
    </div>
    <div class="col-xl-6">
        <section class="card h-100" aria-labelledby="shortcuts-heading">
            <div class="sec-hd border-0"><h2 class="card-ttl" id="shortcuts-heading">Record shortcuts</h2></div>
            <div class="shortcut-list">
                <a href="{{ route('violators.index') }}" class="shortcut"><i class="bi bi-person"></i><span><strong>Active Violators ({{ $stats['active_violators'] }})</strong><small>Review unverified settlements</small></span><i class="bi bi-chevron-right"></i></a>
                <a href="{{ route('violators.index', ['status' => 'archived']) }}" class="shortcut"><i class="bi bi-archive"></i><span><strong>Archived Violators ({{ $stats['archived_violators'] }})</strong><small>View retained violation history</small></span><i class="bi bi-chevron-right"></i></a>
                @if(auth()->user()->isAdmin())<a href="{{ route('reports.period') }}" class="shortcut"><i class="bi bi-file-earmark-bar-graph"></i><span><strong>Generate reports</strong><small>Daily, weekly and monthly reports</small></span><i class="bi bi-chevron-right"></i></a>@endif
            </div>
        </section>
    </div>
</div>
<section class="card mb-4" aria-labelledby="recent-heading">
    <div class="sec-hd"><h2 class="card-ttl" id="recent-heading">Recent violations</h2><a href="{{ route('violations.index') }}" class="text-decoration-none small">View all <i class="bi bi-arrow-right ms-1"></i></a></div>
    <div class="table-responsive"><table class="table mb-0">
        <thead><tr><th scope="col">Violator</th><th scope="col">Offense</th><th scope="col">Date</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
        <tbody>
            @forelse($recentViolations as $violation)
            <tr>
                
                <td>@if($violation->violator)<a class="text-decoration-none fw-medium" href="{{ route('violators.show', $violation->violator) }}">{{ $violation->personDetail('full_name') }}</a>@else<span class="text-muted">Deleted profile</span>@endif</td>
                <td>{{ $violation->violationType?->offense_name ?? 'Unavailable' }}</td>
                <td class="text-nowrap">{{ $violation->violation_date->format('M j, Y') }}</td>
                <td><span class="badge badge-{{ $violation->status }}">{{ $violation->payment_label }}</span></td>
                <td><a class="text-decoration-none fw-medium" href="{{ route('violations.show', $violation) }}" aria-label="View violation {{ $violation->id }}">View</a></td>
            </tr>
            @empty
            <tr><td colspan="5"><div class="empty-state"><i class="bi bi-clipboard2"></i><p>No violations recorded yet.</p>@if(auth()->user()->isEnforcer())<a href="{{ route('violations.create') }}" class="btn btn-primary btn-sm">Record first violation</a>@endif</div></td></tr>
            @endforelse
        </tbody>
    </table></div>
</section>
<details class="card">
    <summary class="p-3 fw-medium">More statistics <span class="text-muted small ms-2">Statuses and top offenses</span></summary>
    <div class="row g-3 px-3 pb-3">
        <div class="col-lg-6"><dl class="row mb-0">
            @foreach(['total_violators' => 'Total violators', 'pending_violations' => 'Not yet verified', 'settled_violations' => 'Settled violations', 'pending_citations' => 'Unverified settlements', 'repeat_offenders' => 'Repeat offenders'] as $key => $label)
            <dt class="col-8 fw-normal py-2">{{ $label }}</dt><dd class="col-4 text-end py-2 mb-0">{{ number_format($stats[$key]) }}</dd>
            @endforeach
        </dl></div>
        <div class="col-lg-6"><h2 class="card-ttl mb-3">Top offenses</h2>
            @forelse($topOffenses->where('violations_count', '>', 0) as $offense)
            <div class="mb-3"><div class="d-flex justify-content-between gap-3 small"><span>{{ $offense->offense_name }}</span><strong>{{ $offense->violations_count }}</strong></div><div class="bar-t"><div class="bar-f" style="width:{{ $offense->violations_count / max(1, $topOffenses->max('violations_count')) * 100 }}%"></div></div></div>
            @empty<p class="text-muted small">No offense data yet.</p>@endforelse
        </div>
    </div>
</details>
@endsection
