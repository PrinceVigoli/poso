@extends('layouts.app')
@section('title', 'User Management')
@section('content')
<div class="card">
    <div class="section-header">
        <div class="d-flex align-items-center gap-2">
            <span class="card-title">Users</span>
            <span class="badge" style="background:rgba(15,61,115,.12);color:#0F3D73">{{ $users->count() }} total</span>
        </div>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus me-1"></i>Add User</a>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>User</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
                @foreach($users as $u)
                @php
                    $pts=explode(' ',$u->name);$ini=strtoupper(substr($pts[0],0,1)).(isset($pts[1])?strtoupper(substr($pts[1],0,1)):'');
                    $avMap=['admin'=>['#E9F0F9','#0F3D73'],'enforcer'=>['#F0FDF4','#166534']];
                    $av=$avMap[$u->role]??['#E9F0F9','#0F3D73'];
                @endphp
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="user-av" style="background:{{ $av[0] }};color:{{ $av[1] }}">{{ $ini }}</div>
                            <div>
                                <div class="fw-semibold" style="color:#11253F">{{ $u->name }}</div>
                                <div style="font-size:11px;color:#93A0B3">{{ $u->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="font-family:monospace;font-size:12.5px;color:#55637A">{{ $u->username }}</td>
                    <td style="color:#55637A">{{ $u->email }}</td>
                    <td><span class="badge badge-{{ $u->role }}">{{ ucfirst($u->role) }}</span></td>
                    <td><span class="badge badge-{{ $u->is_active ? 'active' : 'inactive' }}">{{ $u->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td style="color:#55637A">{{ $u->created_at->format('M d, Y') }}</td>
                    <td><a href="{{ route('admin.users.edit',$u) }}" class="btn btn-outline-secondary btn-sm">Edit</a></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $users->links() }}</div>
@endsection
