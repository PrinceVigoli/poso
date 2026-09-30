@extends('layouts.app')
@section('title', 'Repeat Offenders')

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm" style="width:32px;height:32px;padding:0;display:flex;align-items:center;justify-content:center">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h5 class="mb-0 fw-semibold" style="font-size:15px">Repeat offenders</h5>
    <span class="badge badge-overdue ms-1">{{ $offenders->count() }} violators</span>
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm ms-auto">
        <i class="bi bi-printer me-1"></i> Print
    </button>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Violator</th>
                    <th>License no.</th>
                    <th>Plate</th>
                    <th>Vehicle</th>
                    <th>Contact</th>
                    <th class="text-center">Violations</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($offenders as $i => $v)
                <tr>
                    <td>
                        @if($i < 3)
                            <span style="font-size:16px">{{ ['🥇','🥈','🥉'][$i] }}</span>
                        @else
                            <span style="color:#55637A">{{ $i + 1 }}</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="user-avatar">{{ strtoupper(substr($v->display_name,0,1)) }}{{ strtoupper(substr(strrchr($v->display_name,' '),1,1)) }}</div>
                            <a href="{{ route('violators.show', $v) }}" class="text-decoration-none fw-semibold" style="color:#11253F">{{ $v->display_name }}</a>
                        </div>
                    </td>
                    <td style="color:#55637A">{{ $v->license_no ?? '—' }}</td>
                    <td class="fw-semibold">{{ $v->vehicle_plate ?? '—' }}</td>
                    <td style="color:#55637A">{{ $v->vehicle_type ?? '—' }}</td>
                    <td style="color:#55637A">{{ $v->contact_no ?? '—' }}</td>
                    <td class="text-center">
                        <span class="badge badge-overdue" style="font-size:12px;padding:4px 10px">{{ $v->violations_count }}</span>
                    </td>
                    <td>
                        <a href="{{ route('violators.show', $v) }}" class="btn btn-outline-secondary btn-sm" style="font-size:11px;padding:3px 10px">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <i class="bi bi-check-circle" style="font-size:28px;color:#16A34A"></i>
                        <div style="color:#55637A;margin-top:8px;font-size:13px">No repeat offenders at this time.</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
