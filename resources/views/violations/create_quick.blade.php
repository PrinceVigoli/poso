@extends('layouts.enforcer')
@section('title', 'Record Violation')
@section('content')
@if(session('enforcer_preview'))<div class="alert alert-info">You have an unfinished preview. <a href="{{ route('enforcer.review', ['draft' => session('enforcer_preview.token')]) }}">Continue reviewing it</a>.</div>@endif
<div class="card p-4" style="max-width:760px">
    <p class="text-muted small">Enter details</p>
    <h2 class="card-ttl mb-2">Violator information</h2>
    <p class="text-muted small mb-4">Enter the violator's name, address, offense, and ID confiscated. Preview the details before submitting.</p>
    <form method="POST" action="{{ route('enforcer.preview') }}">
        @csrf
        <div class="row g-3">
            <div class="col-12">
                <label for="full_name" class="form-label">Full name <span class="text-danger">*</span></label>
                <input id="full_name" type="text" name="full_name" class="form-control" maxlength="255" value="{{ old('full_name', session('enforcer_input.full_name', request('full_name'))) }}" placeholder="e.g. Juan dela Cruz" required autofocus>
                <div class="form-text">Possible profiles are suggested by name. Check their details before selecting the same person.</div>
            </div>
            <div class="col-12"><label for="address" class="form-label">Address <span class="text-danger">*</span></label><input class="form-control" id="address" name="address" maxlength="255" value="{{ old('address', session('enforcer_input.address')) }}" required></div>
            <div class="col-sm-6"><label for="vehicle_plate" class="form-label">License plate <span class="text-muted fw-normal">(optional)</span></label><input class="form-control" id="vehicle_plate" name="vehicle_plate" maxlength="20" value="{{ old('vehicle_plate', session('enforcer_input.vehicle_plate')) }}"></div>
            <div class="col-sm-6">
                <label for="confiscated_id" class="form-label">ID confiscated <span class="text-danger">*</span></label>
                <select id="confiscated_id" name="confiscated_id" class="form-select" required>
                    <option value="">Select ID or None</option>
                    @foreach(config('portals.confiscated_ids') as $id)<option value="{{ $id }}" @selected(old('confiscated_id', session('enforcer_input.confiscated_id')) === $id)>{{ $id }}</option>@endforeach
                </select>
            </div>
            <div class="col-12">
                <label for="violation_type_id" class="form-label">Offense <span class="text-danger">*</span></label>
                <select id="violation_type_id" name="violation_type_id" class="form-select" required>
                    <option value="">Select offense</option>
                    @foreach($violationTypes as $type)<option value="{{ $type->id }}" @selected(old('violation_type_id', session('enforcer_input.violation_type_id')) == $type->id)>{{ $type->category }} — {{ $type->offense_name }} &middot; {{ $type->fine_label }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="mt-3"><label for="additional_info" class="form-label">Additional information <span class="text-muted fw-normal">(optional)</span></label><textarea class="form-control" id="additional_info" name="additional_info" maxlength="500" rows="3">{{ old('additional_info', session('enforcer_input.additional_info')) }}</textarea></div>
        <div class="alert alert-info mt-4">You will review these details on the next page. The violation is saved only after you confirm.</div>
        <div class="d-flex flex-wrap gap-2"><button class="btn btn-primary" type="submit"><i class="bi bi-eye me-2"></i>Preview details</button><button class="btn btn-outline-secondary" type="submit" formaction="{{ route('enforcer.clear') }}" formnovalidate>Clear drafts</button></div>
    </form>
</div>
@endsection
