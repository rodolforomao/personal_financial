@extends('layouts.guest')

@section('content')
<p class="login-box-msg">Entre na sua conta financeira</p>
<form action="{{ route('login.attempt') }}" method="POST">
    @csrf
    <div class="input-group mb-3">
        <input type="email" name="email" class="form-control" placeholder="Email" value="{{ old('email') }}" required>
        <div class="input-group-text"><span class="bi bi-envelope"></span></div>
    </div>
    <div class="input-group mb-3">
        <input type="password" name="password" class="form-control" placeholder="Senha" required>
        <div class="input-group-text"><span class="bi bi-lock-fill"></span></div>
    </div>
    <div class="row">
        <div class="col-8">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                <label class="form-check-label" for="remember">Lembrar</label>
            </div>
        </div>
        <div class="col-4">
            <button type="submit" class="btn btn-primary w-100">Entrar</button>
        </div>
    </div>
</form>
<p class="mb-0 mt-3 text-center">
    <a href="{{ route('register') }}">Criar nova conta</a>
</p>
@endsection
