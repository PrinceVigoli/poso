@extends('layouts.enforcer')
@section('title', 'My Submissions')
@section('content')
<form class="mb-4" method="GET"><label class="form-label" for="submissionSearch">Search your submissions by citizen name</label><div class="d-flex gap-2"><input class="form-control" id="submissionSearch" name="search" maxlength="255" value="{{ request('search') }}"><button class="btn btn-primary">Search</button></div></form>
@forelse($records as $record)
<article class="card p-3 mb-3"><div class="d-flex justify-content-between gap-2"><strong class="text-break">{{ $record->personDetail('full_name') }}</strong><span class="badge badge-{{ $record->status }} align-self-start">{{ $record->payment_label }}</span></div>
<p class="my-2">{{ $record->violationType?->offense_name }}</p><p class="small text-muted">Reference #{{ $record->id }} · {{ $record->violation_date->format('M j, Y') }}</p>
<a class="btn btn-outline-primary" href="{{ route('enforcer.show', $record) }}">View record and citizen access code</a></article>
@empty<p class="alert alert-info">No submissions match this search.</p>@endforelse
{{ $records->links() }}
@endsection
