@extends('layouts.app')
@section('title', 'Audit Logs')
@section('content')
<div class="card">
    <div class="section-header">
        <span class="card-title">Audit Logs</span>
        <form method="GET" class="d-flex gap-2">
            <select name="module" class="form-select form-select-sm" style="width:160px">
                <option value="">All Modules</option>
                @foreach(['violators','violations','citations','users','violation_types'] as $m)
                    <option value="{{ $m }}" {{ request('module')==$m?'selected':'' }}>{{ ucfirst($m) }}</option>
                @endforeach
            </select>
            <button class="btn btn-primary btn-sm">Filter</button>
            <a href="{{ route('admin.audit-logs') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table mb-0" style="font-size:12.5px">
            <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Module</th><th>Details</th><th>IP</th></tr></thead>
            <tbody>
                @forelse($logs as $log)
                @php $aMap=['created'=>['#E8F8F0','#166534'],'deleted'=>['#FEF0F0','#9B1C1C'],'updated'=>['#FEF3C7','#92400E']];$am=$aMap[$log->action]??['#F3F4F6','#374151']; @endphp
                <tr>
                    <td style="white-space:nowrap;color:#93A0B3">{{ $log->created_at->format('M d H:i') }}</td>
                    <td class="fw-semibold" style="color:#11253F">{{ $log->user?->name ?? 'System' }}</td>
                    <td><span class="badge" style="background:{{ $am[0] }};color:{{ $am[1] }}">{{ ucfirst($log->action) }}</span></td>
                    <td><span class="badge" style="background:rgba(15,61,115,.12);color:#0F3D73">{{ $log->module }}</span></td>
                    <td style="color:#55637A">{{ $log->details }}</td>
                    <td style="color:#93A0B3;font-family:monospace;font-size:11px">{{ $log->ip_address }}</td>
                </tr>
                @empty
                <tr><td colspan="6"><div class="empty-state"><i class="bi bi-clock-history"></i><p>No logs yet.</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())<div class="px-3 py-2">{{ $logs->links() }}</div>@endif
</div>
@endsection
