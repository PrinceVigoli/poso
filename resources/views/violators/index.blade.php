@extends('layouts.app')
@section('title', 'Violators')
@section('content')
<div class="tab-pills d-inline-flex mb-3" aria-label="Violator status">
    @foreach(['active' => 'Active', 'archived' => 'Archived', 'all' => 'All'] as $value => $label)
        <a href="{{ route('violators.index', array_filter(['status' => $value, 'search' => request('search')])) }}"
           class="tab-pill {{ $status === $value ? 'active' : '' }}" @if($status === $value) aria-current="page" @endif>{{ $label }} <span class="tab-count">{{ $counts[$value] }}</span></a>
    @endforeach
</div>
<p class="text-muted small">Violators with no outstanding violations are archived. Their history is retained, and a new violation returns them to Active.</p>
<div class="card">
    <div class="section-header">
        <div class="search-wrap">
            <i class="bi bi-search"></i>
            <form method="GET" style="display:inline">
                <input type="hidden" name="status" value="{{ $status }}">
                <input type="text" name="search" aria-label="Search profiles by name, plate or license" class="form-control form-control-sm" style="width:260px"
                       placeholder="Search name, plate, license..." value="{{ request('search') }}">
            </form>
        </div>
        <div class="d-flex gap-2">
            @if(request('search'))<a href="{{ route('violators.index', ['status' => $status]) }}" class="btn btn-outline-secondary btn-sm">Clear</a>@endif
            
        </div>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Violator Name</th><th>License No.</th><th>Plate</th><th>Vehicle</th><th>Total violations</th><th>Not yet verified</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($violators as $v)
                @php $pts=explode(' ',$v->display_name);$ini=strtoupper(substr($pts[0],0,1)).(isset($pts[1])?strtoupper(substr($pts[1],0,1)):''); @endphp
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="user-av">{{ $ini }}</div>
                            <div>
                                <a href="{{ route('violators.show',$v) }}" class="text-decoration-none fw-semibold" style="color:#11253F">{{ $v->display_name }}</a>
                                @if($v->violations_count > 0 && $v->unpaid_violations_count === 0)<span class="badge bg-secondary ms-1">Archived</span>@endif
                                @if($v->counted_violations_count >= \App\Models\Violator::repeatOffenderThreshold())<span class="badge badge-repeat ms-1" style="font-size:10px">Repeat</span>@endif
                                <div style="font-size:11px;color:#93A0B3">{{ $v->address ?? 'No address' }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="color:#55637A">{{ $v->license_no ?? '—' }}</td>
                    <td class="fw-semibold" style="color:#11253F">{{ $v->vehicle_plate ?? '—' }}</td>
                    <td style="color:#55637A">{{ $v->vehicle_type ?? '—' }}</td>
                    <td>
                        <span class="badge" style="{{ $v->violations_count>0 ? 'background:#FBEAEA;color:#9B1C1C' : 'background:#F1F3F5;color:#55637A' }}">
                            {{ $v->violations_count }}
                        </span>
                    </td>
                    <td><span class="badge {{ $v->unpaid_violations_count > 0 ? 'badge-pending' : 'badge-settled' }}">{{ $v->unpaid_violations_count }}</span></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="{{ route('violators.show',$v) }}" class="btn btn-outline-secondary btn-sm">View</a>
                            
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7"><div class="empty-state"><i class="bi bi-people"></i><p>No violators found.</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($violators->hasPages())<div class="px-3 py-2">{{ $violators->links() }}</div>@endif
</div>
@endsection
