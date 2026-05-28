@extends('layouts.guest')

@section('content')
<p class="login-box-msg">Defina uma nova senha</p>
<form action="{{ route('password.update') }}" method="POST">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="input-group mb-3">
        <input type="email" name="email" class="form-control" placeholder="Email" value="{{ old('email', $email) }}" required>
        <div class="input-group-text"><span class="bi bi-envelope"></span></div>
    </div>
    <div class="input-group mb-3">
        <input type="password" name="password" class="form-control" placeholder="Nova senha" required>
        <div class="input-group-text"><span class="bi bi-lock-fill"></span></div>
    </div>
    <div class="input-group mb-3">
        <input type="password" name="password_confirmation" class="form-control" placeholder="Confirme a nova senha" required>
        <div class="input-group-text"><span class="bi bi-shield-check"></span></div>
    </div>
    <button type="submit" class="btn btn-primary w-100">Redefinir senha</button>
</form>
@endsection
