@extends('layouts.app')
@section('title', 'Add Violator')

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('violators.index') }}" class="btn btn-outline-secondary btn-sm" style="width:32px;height:32px;padding:0;display:flex;align-items:center;justify-content:center">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h5 class="mb-0 fw-semibold" style="font-size:15px">Add new violator</h5>
</div>

<div class="card p-4" style="max-width:640px">
    <form method="POST" action="{{ route('violators.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Full name (Last name, First name) <span class="text-danger">*</span></label>
                <input type="text" name="full_name" class="form-control @error('full_name') is-invalid @enderror"
                       value="{{ old('full_name') }}" placeholder="e.g. dela Cruz, Juan" required>
                @error('full_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">License number</label>
                <input type="text" name="license_no" class="form-control" value="{{ old('license_no') }}" placeholder="e.g. N01-23-456789">
            </div>
            <div class="col-md-6">
                <label class="form-label">Date of birth</label>
                <input type="date" name="birthdate" class="form-control" value="{{ old('birthdate') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Vehicle plate</label>
                <input type="text" name="vehicle_plate" class="form-control" value="{{ old('vehicle_plate') }}" placeholder="e.g. ABC 1234">
            </div>
            <div class="col-md-6">
                <label class="form-label">Vehicle type</label>
                <select name="vehicle_type" class="form-select">
                    <option value="">— Select —</option>
                    @foreach(['Motorcycle','Tricycle'] as $t)
                        <option value="{{ $t }}" {{ old('vehicle_type')==$t?'selected':'' }}>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Address (Barangay, Luna, Apayao)</label>
                <select name="address" class="form-select">
                    <option value="">— Select barangay —</option>
                    @foreach(['Bacsay','Cagandungan','Calabigan','Cangisitan','Capagaypayan','Dagupan','Lappa','Luyon','Marag','Poblacion','Quirino','Salvacion','San Francisco','San Gregorio','San Isidro Norte','San Isidro Sur','San Sebastian','Santa Lina','Shalom','Tumog','Turod','Zumigui'] as $b)
                        <option value="Brgy. {{ $b }}, Luna, Apayao" {{ old('address')=="Brgy. {$b}, Luna, Apayao"?'selected':'' }}>Brgy. {{ $b }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Contact number</label>
                <input type="text" name="contact_no" class="form-control" value="{{ old('contact_no') }}" placeholder="09XXXXXXXXX">
            </div>
        </div>
        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check me-1"></i> Save violator</button>
            <a href="{{ route('violators.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
