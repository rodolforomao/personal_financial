@extends('layouts.guest')

@section('content')
<p class="login-box-msg">Cadastro aguardando liberação</p>

<div class="alert alert-warning small">
    Olá, {{ $user->name }}. Seu acesso ainda não está ativo.
</div>

<div class="card border-0 bg-body-tertiary mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-3">
            <div>
                <div class="fw-semibold">{{ $profile->name }}</div>
                <div class="text-muted small">{{ $profile->description }}</div>
            </div>
            <span class="badge text-bg-primary">{{ $profile->monthlyPriceLabel() }}/mês</span>
        </div>
    </div>
</div>

<p class="text-muted small">
    Depois que o pagamento for confirmado, sua conta será liberada automaticamente. Um administrador também pode liberar
    seu acesso manualmente para casos específicos.
</p>

<form action="{{ route('logout') }}" method="POST">
    @csrf
    <button type="submit" class="btn btn-outline-secondary w-100">Sair</button>
</form>
@endsection
