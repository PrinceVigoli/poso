@extends('layouts.app')
@section('title', 'Edit Violator')

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('violators.show', $violator) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i><span class="visually-hidden">Back</span></a>
    <h5 class="mb-0">Edit Violator — {{ $violator->display_name }}</h5>
</div>

<div class="card stat-card p-4" style="max-width:680px">
    <form method="POST" action="{{ route('violators.update', $violator) }}">
        @csrf @method('PUT')
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="full_name" class="form-control @error('full_name') is-invalid @enderror"
                       value="{{ old('full_name', $violator->display_name) }}" required>
                @error('full_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">License Number</label>
                <input type="text" name="license_no" class="form-control" value="{{ old('license_no', $violator->license_no) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Date of Birth</label>
                <input type="date" name="birthdate" class="form-control" value="{{ old('birthdate', $violator->birthdate) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Vehicle Plate</label>
                <input type="text" name="vehicle_plate" class="form-control" value="{{ old('vehicle_plate', $violator->vehicle_plate) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Vehicle Type</label>
                <select name="vehicle_type" class="form-select">
                    <option value="">— Select —</option>
                    @foreach(['Motorcycle','Tricycle'] as $t)
                        <option value="{{ $t }}" {{ old('vehicle_type', $violator->vehicle_type) == $t ? 'selected' : '' }}>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Address (Barangay, Luna, Apayao)</label>
                <select name="address" class="form-select">
                    <option value="">— Select barangay —</option>
                    @foreach(['Bacsay','Cagandungan','Calabigan','Cangisitan','Capagaypayan','Dagupan','Lappa','Luyon','Marag','Poblacion','Quirino','Salvacion','San Francisco','San Gregorio','San Isidro Norte','San Isidro Sur','San Sebastian','Santa Lina','Shalom','Tumog','Turod','Zumigui'] as $b)
                        <option value="Brgy. {{ $b }}, Luna, Apayao" {{ old('address', $violator->address)=="Brgy. {$b}, Luna, Apayao"?'selected':'' }}>Brgy. {{ $b }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Contact Number</label>
                <input type="text" name="contact_no" class="form-control" value="{{ old('contact_no', $violator->contact_no) }}">
            </div>
        </div>
        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check me-1"></i> Update</button>
            <a href="{{ route('violators.show', $violator) }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
