@extends('layouts.app')
@section('title', $user ? 'Edit User' : 'Add User')

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('admin.users') }}" class="btn btn-outline-secondary btn-sm" style="width:32px;height:32px;padding:0;display:flex;align-items:center;justify-content:center">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h5 class="mb-0 fw-semibold" style="font-size:15px">{{ $user ? 'Edit — '.$user->name : 'Add new user' }}</h5>
</div>

<div class="card p-4" style="max-width:760px">
    <form method="POST" action="{{ $user ? route('admin.users.update', $user) : route('admin.users.store') }}">
        @csrf
        @if($user) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label" for="user_name">Full name <span class="text-danger">*</span></label>
                <input type="text" name="name" id="user_name" class="form-control @error('name') is-invalid @enderror"
                       value="{{ old('name', $user?->name) }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="user_username">Username <span class="text-danger">*</span></label>
                <input type="text" name="username" id="user_username" class="form-control @error('username') is-invalid @enderror"
                       value="{{ old('username', $user?->username) }}" required>
                @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="user_role">Role <span class="text-danger">*</span></label>
                <select name="role" id="user_role" class="form-select" required>
                    @foreach(['admin','enforcer'] as $r)
                        <option value="{{ $r }}" {{ old('role', $user?->role) == $r ? 'selected' : '' }}>{{ ucfirst($r) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="user_email">Email <span class="text-danger">*</span></label>
                <input type="email" name="email" id="user_email" class="form-control @error('email') is-invalid @enderror"
                       value="{{ old('email', $user?->email) }}" required>
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="user_password">Password {{ $user ? '(leave blank to keep)' : '' }} <span class="text-danger">{{ $user ? '' : '*' }}</span></label>
                <input type="password" name="password" id="user_password" minlength="15" maxlength="128" autocomplete="new-password" class="form-control @error('password') is-invalid @enderror"
                       {{ $user ? '' : 'required' }}>
                <div class="form-text">Use 15–128 characters. Changing access details signs out existing sessions.</div>
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="user_password_confirmation">Confirm password</label>
                <input type="password" name="password_confirmation" id="user_password_confirmation" class="form-control">
            </div>
            @if($user)
            <div class="col-12">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" name="is_active" id="is_active" value="1"
                           {{ old('is_active', $user->is_active) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active" style="font-size:13px">Account is active</label>
                </div>
            </div>
            @endif
        </div>
        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check me-1"></i> {{ $user ? 'Update user' : 'Create user' }}
            </button>
            <a href="{{ route('admin.users') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
