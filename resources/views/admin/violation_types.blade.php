@extends('layouts.app')
@section('title', 'Offense Types')

@section('content')
<p class="text-muted small">Manage current municipal offenses and ordinance fine amounts. Editing a used offense creates a new version; deleting it removes it from new records while preserving history.</p>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h5 class="mb-0">Offense Types</h5>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus me-1"></i> Add Offense
    </button>
</div>

<div class="card stat-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Offense Name</th>
                    <th>Category</th><th>Fine Amount</th>
                    <th>Used</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($types as $t)
                <tr>
                    <td><strong>{{ $t->offense_name }}</strong></td>
                    <td>{{ $t->category }}</td><td>{{ $t->fine_label }}</td>
                    <td><span class="badge bg-light text-dark">{{ $t->violations_count }}x</span></td>
                    <td>
                        {{-- trigger button only inside tbody, modal is outside --}}
                        <button class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:12px"
                                data-bs-toggle="modal"
                                data-bs-target="#editModal"
                                data-id="{{ $t->id }}" data-url="{{ route('admin.violation-types.update', $t) }}"
                                data-name="{{ $t->offense_name }}" data-category="{{ $t->category }}"
                                data-fine="{{ $t->fine_amount }}"
                                data-desc="{{ $t->description }}">
                            Edit
                        </button>
                        <form method="POST" action="{{ route('admin.violation-types.destroy', $t) }}" class="d-inline" onsubmit="return confirm('Remove this offense from new records? Existing history will be retained.')">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm">Delete</button></form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- ── ADD MODAL (outside table) ─────────────────────────── --}}
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.violation-types.store') }}">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title">Add Offense Type</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Offense Name <span class="text-danger">*</span></label>
                        <input type="text" name="offense_name" class="form-control" required>
                    </div>
                    <div class="mb-3"><label class="form-label" for="add_category">Category</label><select name="category" id="add_category" class="form-select" required>@foreach(config('portals.offense_categories') as $category)<option value="{{ $category }}">{{ $category }}</option>@endforeach</select></div>
                    <div class="mb-3">
                        <label class="form-label">Fine Amount (₱, optional)</label>
                        <input type="number" name="fine_amount" class="form-control" min="0" step="0.01" placeholder="Not configured">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Add Offense</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── SHARED EDIT MODAL (outside table, populated by JS) ── --}}
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editForm" action="">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h6 class="modal-title">Edit Offense Type</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Offense Name <span class="text-danger">*</span></label>
                        <input type="text" name="offense_name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3"><label class="form-label" for="edit_category">Category</label><select name="category" id="edit_category" class="form-select" required>@foreach(config('portals.offense_categories') as $category)<option value="{{ $category }}">{{ $category }}</option>@endforeach</select></div>
                    <div class="mb-3">
                        <label class="form-label">Fine Amount (₱, optional)</label>
                        <input type="number" name="fine_amount" id="edit_fine" class="form-control" min="0" step="0.01" placeholder="Not configured">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_desc" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// Populate the shared edit modal with row data
document.getElementById('editModal').addEventListener('show.bs.modal', function (event) {
    const btn      = event.relatedTarget;
    const id       = btn.dataset.id;
    const name     = btn.dataset.name;
    const fine     = btn.dataset.fine;
    const desc     = btn.dataset.desc;

    // Build the correct PUT URL
    document.getElementById('editForm').action = btn.dataset.url;
    document.getElementById('edit_category').value = btn.dataset.category;
    document.getElementById('edit_name').value     = name;
    document.getElementById('edit_fine').value     = fine;
    document.getElementById('edit_desc').value     = desc;
});
</script>
@endpush
