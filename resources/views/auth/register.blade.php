@extends('layouts.guest')

@section('content')
<p class="login-box-msg">Crie sua conta financeira</p>

<div class="alert alert-info small">
    O plano inicial custa <strong>{{ $profile->monthlyPriceLabel() }}/mês</strong>. O acesso fica pendente até o pagamento
    ser confirmado ou um administrador liberar sua conta.
</div>

<form action="{{ route('register.store') }}" method="POST">
    @csrf
    <div class="input-group mb-3">
        <input type="text" name="name" class="form-control" placeholder="Nome" value="{{ old('name') }}" required>
        <div class="input-group-text"><span class="bi bi-person"></span></div>
    </div>
    <div class="input-group mb-3">
        <input type="email" name="email" class="form-control" placeholder="Email" value="{{ old('email') }}" required>
        <div class="input-group-text"><span class="bi bi-envelope"></span></div>
    </div>
    <div class="input-group mb-3">
        <input type="text" name="phone" class="form-control" placeholder="Telefone" value="{{ old('phone') }}">
        <div class="input-group-text"><span class="bi bi-phone"></span></div>
    </div>
    <div class="input-group mb-3">
        <input type="password" name="password" class="form-control" placeholder="Senha" required>
        <div class="input-group-text"><span class="bi bi-lock-fill"></span></div>
    </div>
    <div class="input-group mb-3">
        <input type="password" name="password_confirmation" class="form-control" placeholder="Confirmar senha" required>
        <div class="input-group-text"><span class="bi bi-lock"></span></div>
    </div>
    <button type="submit" class="btn btn-primary w-100 mb-3">Cadastrar</button>
</form>

<p class="mb-0 text-center">
    <a href="{{ route('login') }}">Já tenho conta</a>
</p>
@endsection
