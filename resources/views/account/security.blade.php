@extends('layouts.adminlte')

@section('title', 'Conta e segurança')
@section('page_title', 'Conta e segurança')
@section('breadcrumb')
    <li class="breadcrumb-item active">Conta</li>
@endsection

@section('content')
@if($plainTextToken ?? null)
    <div class="alert alert-warning">
        <strong>Token de API criado.</strong> Copie agora — não será exibido novamente:
        <code class="user-select-all d-block mt-2 p-2 bg-dark text-light rounded">{{ $plainTextToken }}</code>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="bi bi-display me-2"></i>Sessões ativas</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Dispositivo</th>
                                <th>IP</th>
                                <th>Última atividade</th>
                                <th class="text-end">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sessions as $session)
                                <tr @class(['table-primary' => $session->is_current])>
                                    <td>
                                        <div class="small text-break">{{ $session->user_agent ?: 'Desconhecido' }}</div>
                                        @if($session->is_current)
                                            <span class="badge text-bg-primary">Sessão atual</span>
                                        @endif
                                    </td>
                                    <td class="text-muted small">{{ $session->ip_address ?: '—' }}</td>
                                    <td class="small">
                                        {{ $session->last_activity ? \Illuminate\Support\Carbon::createFromTimestamp($session->last_activity)->diffForHumans() : '—' }}
                                    </td>
                                    <td class="text-end">
                                        <form action="{{ route('account.sessions.destroy', $session->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                @if($session->is_current) onclick="return confirm('Encerrar a sessão atual? Você será deslogado.');" @endif>
                                                Revogar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        Nenhuma sessão registrada (driver de sessão pode não usar banco de dados).
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0"><i class="bi bi-key me-2"></i>Tokens de API</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('account.tokens.store') }}" method="POST" class="row g-2 mb-3">
                    @csrf
                    <div class="col-8">
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="Nome do token (ex. integração)" required maxlength="120">
                    </div>
                    <div class="col-4">
                        <button type="submit" class="btn btn-primary btn-sm w-100">Criar token</button>
                    </div>
                </form>

                <ul class="list-group list-group-flush">
                    @forelse($tokens as $token)
                        <li class="list-group-item px-0 d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="fw-semibold">{{ $token->name }}</div>
                                <div class="text-muted small">
                                    Criado {{ $token->created_at?->format('d/m/Y H:i') ?? '—' }}
                                    @if($token->last_used_at)
                                        · Último uso {{ $token->last_used_at->diffForHumans() }}
                                    @endif
                                </div>
                            </div>
                            <form action="{{ route('account.tokens.destroy', $token->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Revogar</button>
                            </form>
                        </li>
                    @empty
                        <li class="list-group-item px-0 text-muted small">Nenhum token pessoal. Use tokens para integrações via API Sanctum.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="card card-outline card-secondary">
            <div class="card-body small text-muted mb-0">
                <strong>Dica:</strong> envie o header <code>X-Workspace-Id</code> nas chamadas à API junto com o token Bearer.
            </div>
        </div>
    </div>
</div>
@endsection
