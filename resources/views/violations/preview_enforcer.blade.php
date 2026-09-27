@extends('layouts.enforcer')
@section('title', 'Review Before Submitting')
@section('content')
<div class="card p-4" style="max-width:760px">
    <p class="text-muted small">Preview and confirm</p>
    <h2 class="card-ttl mb-3">Please check every detail</h2>
    <div class="alert alert-info">Nothing has been saved yet. Confirm below to save the violation record.</div>
    @if($violator)
        <p class="small">Using the existing profile for <strong>{{ $violator->full_name }}</strong>. You selected this person explicitly. These details will be saved with this incident; earlier records and the profile will remain unchanged.</p>
    @else
        <p class="small text-muted">A new violator profile will be created after confirmation.</p>
    @endif
    <dl class="row mb-3">
        <dt class="col-sm-4 mb-2">TOP (ticket number)</dt><dd class="col-sm-8 mb-3">{{ $draft['top_number'] ?? 'Not recorded' }}</dd>
        @foreach(['full_name' => 'Full name', 'address' => 'Address', 'vehicle_plate' => 'License plate'] as $field => $label)
            <dt class="col-sm-4 mb-2">{{ $label }}</dt><dd class="col-sm-8 mb-3 text-break">{{ $draft['profile'][$field] ?: 'Not provided' }}</dd>
        @endforeach
        <dt class="col-sm-4 mb-2">Apprehension location</dt><dd class="col-sm-8 mb-3 text-break">{{ $draft['location'] ?? 'Not recorded' }}</dd>
        <dt class="col-sm-4 mb-2">ID confiscated</dt><dd class="col-sm-8 mb-3">{{ $draft['confiscated_id'] ?? 'Not recorded' }}</dd>
        <dt class="col-sm-4 mb-2">Additional information</dt><dd class="col-sm-8 mb-3 text-break">{{ $draft['additional_info'] ?? 'Not provided' }}</dd>
        <dt class="col-sm-4 mb-2">Offense</dt><dd class="col-sm-8 mb-3">{{ $type->offense_name }}</dd>
        <dt class="col-sm-4 mb-2">Fine</dt><dd class="col-sm-8 mb-3">{{ $draft['fine_amount'] === '' ? 'Not configured' : "\u{20B1}".number_format($draft['fine_amount'], 2) }}</dd>
        <dt class="col-sm-4 mb-2">Date</dt><dd class="col-sm-8 mb-3">{{ \Carbon\Carbon::parse($draft['violation_date'])->format('M j, Y') }}</dd>
        <dt class="col-sm-4 mb-2">Recorded by</dt><dd class="col-sm-8 mb-3">{{ auth()->user()->name }}</dd>
    </dl>
    @if(!empty($draft['minor_photos']))
        <p class="fw-semibold">Pictures for minors</p>
        <div class="d-flex flex-wrap gap-2 mb-3">
            @foreach($draft['minor_photos'] as $index => $path)
                <a href="{{ route('enforcer.photo', ['token' => $draft['input']['photo_token'], 'photo' => $index]) }}" target="_blank" rel="noopener"><img src="{{ route('enforcer.photo', ['token' => $draft['input']['photo_token'], 'photo' => $index]) }}" alt="Attached picture {{ $index + 1 }}" style="width:120px;height:120px;object-fit:cover" class="rounded"></a>
            @endforeach
        </div>
    @endif
    @if(count($draft['today_ids']) > 0)<div class="alert alert-warning">This person already has {{ count($draft['today_ids']) }} violation(s) today. Confirm only if this is a separate incident.</div>@endif
    <form method="POST" action="{{ route('enforcer.confirm') }}" id="confirmEnforcerForm">
        @csrf
        <input type="hidden" name="preview_token" value="{{ $draft['token'] }}">
        <div class="form-check mb-4">
            <input type="checkbox" name="confirmed" value="1" class="form-check-input" id="confirmed" required>
            <label class="form-check-label" for="confirmed">I have checked the details and confirm they are correct.</label>
        </div>
        <button type="submit" class="btn btn-primary w-100" id="confirmEnforcerButton"><i class="bi bi-check2-circle me-2"></i>Confirm and submit</button>
    </form>
    <form method="POST" action="{{ route('enforcer.edit') }}" class="mt-2">@csrf<input type="hidden" name="preview_token" value="{{ $draft['token'] }}"><button class="btn btn-outline-secondary w-100">Back to edit details</button></form>
</div>
@endsection
@push('scripts')
<script>
document.getElementById('confirmEnforcerForm').addEventListener('submit', function () {
    const button = document.getElementById('confirmEnforcerButton');
    button.disabled = true;
    button.textContent = 'Submitting...';
});
window.addEventListener('pageshow', function () {
    const button = document.getElementById('confirmEnforcerButton');
    button.disabled = false;
    button.textContent = 'Confirm and submit';
});
</script>
@endpush
