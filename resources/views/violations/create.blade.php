@extends('layouts.app')
@section('title', 'Record Violation')

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('violations.index') }}" class="btn btn-outline-secondary btn-sm" style="width:32px;height:32px;padding:0;display:flex;align-items:center;justify-content:center">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h5 class="mb-0 fw-semibold" style="font-size:15px">Record new violation</h5>
</div>

<div class="card p-4" style="max-width:640px">
    <form method="POST" action="{{ route('violations.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Violator <span class="text-danger">*</span></label>
                <select name="violator_id" class="form-select @error('violator_id') is-invalid @enderror" required>
                    <option value="">— Select violator —</option>
                    @foreach($violators as $vl)
                        <option value="{{ $vl->id }}"
                            {{ (old('violator_id', request('violator_id')) == $vl->id) ? 'selected' : '' }}>
                            {{ $vl->display_name }} — {{ $vl->vehicle_plate ?? 'No plate' }}
                        </option>
                    @endforeach
                </select>
                @error('violator_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="mt-1">
                    <a href="{{ route('violators.create') }}" style="font-size:11.5px;color:#0F3D73">
                        <i class="bi bi-plus"></i> Add new violator first
                    </a>
                </div>
            </div>

            <div class="col-12">
                <label class="form-label">Offense type <span class="text-danger">*</span></label>
                <select name="violation_type_id" id="typeSelect"
                        class="form-select @error('violation_type_id') is-invalid @enderror" required>
                    <option value="">— Select offense —</option>
                    @foreach($violationTypes as $t)
                        <option value="{{ $t->id }}"
                                data-fine="{{ $t->fine_amount }}"
                                {{ old('violation_type_id') == $t->id ? 'selected' : '' }}>
                            {{ $t->offense_name }} — {{ $t->fine_label }}
                        </option>
                    @endforeach
                </select>
                @error('violation_type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label class="form-label">Fine amount</label>
                <div class="input-group">
                    <span class="input-group-text" style="font-size:12.5px;background:#F1F3F5;border-color:#DFE4EA">₱</span>
                    <input type="text" id="fineDisplay" class="form-control" readonly
                           placeholder="Auto-filled from offense" style="background:#F1F3F5;color:#55637A">
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label">Violation date <span class="text-danger">*</span></label>
                <input type="date" name="violation_date"
                       class="form-control @error('violation_date') is-invalid @enderror"
                       value="{{ old('violation_date', today()->toDateString()) }}" required>
                @error('violation_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label class="form-label">Location (Barangay) <span class="text-danger">*</span></label>
                <select name="location" class="form-select @error('location') is-invalid @enderror" required>
                    <option value="">— Select barangay —</option>
                    @foreach(['Bacsay','Cagandungan','Calabigan','Cangisitan','Capagaypayan','Dagupan','Lappa','Luyon','Marag','Poblacion','Quirino','Salvacion','San Francisco','San Gregorio','San Isidro Norte','San Isidro Sur','San Sebastian','Santa Lina','Shalom','Tumog','Turod','Zumigui'] as $b)
                        <option value="Brgy. {{ $b }}, Luna, Apayao" {{ old('location')=="Brgy. {$b}, Luna, Apayao"?'selected':'' }}>Brgy. {{ $b }}</option>
                    @endforeach
                </select>
                @error('location')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label class="form-label">Remarks / notes</label>
                <textarea name="remarks" class="form-control" rows="3"
                          placeholder="Optional additional notes...">{{ old('remarks') }}</textarea>
            </div>

            <div class="col-12">
                <div class="alert alert-info mb-0" style="font-size:12px">
                    <i class="bi bi-info-circle me-1"></i>
                    Settlement status is tracked after saving. Treasury receipts are verified by POSO.
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check me-1"></i> Record violation
            </button>
            <a href="{{ route('violations.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('typeSelect').addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    const fine = opt.dataset.fine;
    document.getElementById('fineDisplay').value = fine
        ? parseFloat(fine).toLocaleString('en-PH', { minimumFractionDigits: 2 })
        : 'Not configured';
});
document.getElementById('typeSelect').dispatchEvent(new Event('change'));
</script>
@endpush
