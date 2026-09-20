@extends('layouts.app')
@section('title', 'Edit Violation #' . $violation->id)

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('violations.show', $violation) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i><span class="visually-hidden">Back</span></a>
    <h5 class="mb-0">Edit Violation #{{ $violation->id }}</h5>
</div>

<div class="card stat-card p-4" style="max-width:680px">
    <form method="POST" action="{{ route('violations.update', $violation) }}">
        @csrf @method('PUT')
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Violator <span class="text-danger">*</span></label>
                <select name="violator_id" class="form-select" required>
                    @foreach($violators as $vl)
                        <option value="{{ $vl->id }}" {{ old('violator_id', $violation->violator_id) == $vl->id ? 'selected' : '' }}>
                            {{ $vl->full_name }} — {{ $vl->vehicle_plate ?? 'No plate' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Offense Type <span class="text-danger">*</span></label>
                <select name="violation_type_id" class="form-select" required>
                    @foreach($violationTypes as $t)
                        <option value="{{ $t->id }}" {{ old('violation_type_id', $violation->violation_type_id) == $t->id ? 'selected' : '' }}>
                            {{ $t->offense_name }} — {{ $t->fine_label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Violation Date <span class="text-danger">*</span></label>
                <input type="date" name="violation_date" class="form-control"
                       value="{{ old('violation_date', $violation->violation_date->toDateString()) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Status <span class="text-danger">*</span></label>
                <select name="status" class="form-select" required>
                    @foreach($violation->citation?->payment_status === 'paid' ? ['settled'] : ['pending','dismissed'] as $s)
                        <option value="{{ $s }}" {{ old('status', $violation->status) == $s ? 'selected' : '' }}>
                            {{ ucfirst($s) }}
                        </option>
                    @endforeach
                </select>
                <small class="text-muted">Paid status requires admin verification of a Treasury receipt.</small>
            </div>
            <div class="col-12">
                <label class="form-label">Location (Barangay) <span class="text-danger">*</span></label>
                <select name="location" class="form-select" required>
                    <option value="">— Select barangay —</option>
                    @foreach(['Bacsay','Cagandungan','Calabigan','Cangisitan','Capagaypayan','Dagupan','Lappa','Luyon','Marag','Poblacion','Quirino','Salvacion','San Francisco','San Gregorio','San Isidro Norte','San Isidro Sur','San Sebastian','Santa Lina','Shalom','Tumog','Turod','Zumigui'] as $b)
                        <option value="Brgy. {{ $b }}, Luna, Apayao" {{ old('location', $violation->location)=="Brgy. {$b}, Luna, Apayao"?'selected':'' }}>Brgy. {{ $b }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Remarks</label>
                <textarea name="remarks" class="form-control" rows="3">{{ old('remarks', $violation->remarks) }}</textarea>
            </div>
        </div>
        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check me-1"></i> Update</button>
            <a href="{{ route('violations.show', $violation) }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
