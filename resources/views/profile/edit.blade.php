@extends(auth()->user()->isEnforcer() ? 'layouts.enforcer' : 'layouts.app')
@section('title', 'My Profile')

@section('content')
<div class="card p-4" style="max-width:640px">
    <form method="POST" action="{{ route('profile.update') }}">
        @csrf
        @method('PUT')

        <div class="mb-4">
            <label class="form-label" for="profile_name">Full name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="profile_name" class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name', $user->name) }}" maxlength="255" required autocomplete="name">
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <dl class="row mb-4 small">
            <dt class="col-sm-4 text-muted fw-normal">Username</dt>
            <dd class="col-sm-8">{{ $user->username }}</dd>
            <dt class="col-sm-4 text-muted fw-normal">Role</dt>
            <dd class="col-sm-8">{{ ucfirst($user->role) }}</dd>
        </dl>
        <p class="text-muted small">Your username and role are managed by an administrator.</p>

        <hr class="my-4">

        <h6 class="fw-semibold" style="font-size:14px">Change password</h6>
        <p class="text-muted small">Leave these blank to keep your current password.</p>

        <div class="mb-3">
            <label class="form-label" for="current_password">Current password</label>
            <input type="password" name="current_password" id="current_password"
                   class="form-control @error('current_password') is-invalid @enderror" autocomplete="current-password">
            @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label" for="new_password">New password</label>
            <input type="password" name="password" id="new_password"
                   class="form-control @error('password') is-invalid @enderror"
                   minlength="15" maxlength="128" autocomplete="new-password"
                   aria-describedby="passwordHelp">
            <div id="passwordHelp" class="form-text">At least 15 characters.</div>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-4">
            <label class="form-label" for="password_confirmation">Confirm new password</label>
            <input type="password" name="password_confirmation" id="password_confirmation"
                   class="form-control" minlength="15" maxlength="128" autocomplete="new-password">
        </div>

        <p class="text-muted small">Changing your password signs you out of any other device.</p>

        <button type="submit" class="btn btn-primary">Save changes</button>
    </form>
</div>
@endsection
