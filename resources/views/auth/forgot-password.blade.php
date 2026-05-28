@extends('layouts.guest')

@section('content')
<p class="login-box-msg">Recupere o acesso da sua conta</p>
<form action="{{ route('password.email') }}" method="POST">
    @csrf
    <div class="input-group mb-3">
        <input type="email" name="email" class="form-control" placeholder="Email" value="{{ old('email') }}" required>
        <div class="input-group-text"><span class="bi bi-envelope"></span></div>
    </div>
    <button type="submit" class="btn btn-primary w-100">Enviar link de redefinição</button>
</form>
<p class="mb-0 mt-3 text-center">
    <a href="{{ route('login') }}">Voltar para login</a>
</p>
@endsection
