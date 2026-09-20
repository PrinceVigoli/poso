@extends('layouts.app')
@section('title', 'Reports')
@section('content')
<p class="text-muted mb-4">Print apprehension and settlement reports. Settlement dates reflect POSO verification of Treasury receipts.</p>
<div class="row g-3">
@foreach(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $period => $label)
<div class="col-md-4"><div class="card p-4 h-100"><i class="bi bi-calendar3 fs-3 text-primary mb-3"></i><h2 class="card-ttl mb-2">{{ $label }} report</h2><p class="text-muted small">{{ $period === 'daily' ? 'Records for one selected day.' : ($period === 'weekly' ? 'Records for a Monday to Sunday week.' : 'Records for one calendar month.') }}</p><a class="btn btn-primary mt-auto" href="{{ route('reports.period', ['period' => $period]) }}">View report</a></div></div>
@endforeach
</div>
@endsection
