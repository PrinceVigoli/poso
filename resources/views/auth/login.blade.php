@extends('layouts.public')
@section('title', 'Sign in')
@section('content')
<div class="login-grid">
    <section class="login-intro"><h1>POSO Violation<br>Monitoring System</h1><p>Monitor violation records and track fine settlement status.</p><p>Public Order &amp; Safety Office,<br>Municipality of Luna.</p><div class="login-rule"></div><p class="login-note">Administrators and enforcers use their assigned accounts.</p></section>
    <section class="card login-card" aria-labelledby="login-heading">
        <h2 id="login-heading">Welcome back</h2><p class="text-muted mb-4">Sign in to your POSO account</p>
        @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('login.submit') }}">
            @csrf
            <div class="mb-3"><label class="form-label" for="username">Username</label><input class="form-control" id="username" name="username" value="{{ old('username') }}" autocomplete="username" placeholder="Enter your username" required autofocus></div>
            <div class="mb-3"><label class="form-label" for="password">Password</label><div class="password-wrap"><input class="form-control" type="password" id="password" name="password" autocomplete="current-password" placeholder="Enter your password" required><button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false" id="passwordToggle"><i class="bi bi-eye" aria-hidden="true"></i></button></div></div>
            <div class="form-check my-4"><input type="checkbox" class="form-check-input" name="remember" id="remember" value="1" @checked(old('remember'))><label class="form-check-label" for="remember">Keep me signed in</label></div>
            <button type="submit" class="btn btn-primary w-100 py-2">Sign in</button>
        </form>
        <p class="text-muted small mt-4 mb-0">Need access? Contact your POSO administrator.</p>
    </section>
</div>
@endsection
@push('scripts')
<script>
const toggle = document.getElementById('passwordToggle');
toggle.addEventListener('click', () => {
    const input = document.getElementById('password');
    const show = input.type === 'password'; input.type = show ? 'text' : 'password';
    toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    toggle.setAttribute('aria-pressed', String(show));
    toggle.firstElementChild.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
});
</script>
@endpush
