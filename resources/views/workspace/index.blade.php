@extends('layouts.adminlte')

@section('title', 'Workspaces')
@section('page_title', 'Workspaces')
@section('breadcrumb')
    <li class="breadcrumb-item active">Workspaces</li>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0"><i class="bi bi-building-check me-2"></i>Workspace ativo</h3>
                <form action="{{ route('workspace.reset') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Restaura o primeiro workspace da sua conta">
                        Restaurar padrão
                    </button>
                </form>
            </div>
            <div class="card-body">
                @if($currentWorkspace)
                    <p class="mb-1"><strong>{{ $currentWorkspace->name }}</strong></p>
                    <p class="text-muted small mb-3">
                        #{{ $currentWorkspace->id }} · {{ $currentWorkspace->currency }}
                        · Papel: {{ $currentWorkspace->pivot->role ?? 'member' }}
                    </p>
                @else
                    <p class="text-muted">Nenhum workspace selecionado na sessão.</p>
                @endif

                <h6 class="text-muted text-uppercase small fw-bold mb-2">Alternar workspace</h6>
                @if($workspaces->isEmpty())
                    <p class="text-muted small mb-0">Você não participa de nenhum workspace.</p>
                @else
                    <div class="list-group">
                        @foreach($workspaces as $ws)
                            <div class="list-group-item d-flex justify-content-between align-items-center @if(($currentWorkspace->id ?? null) === $ws->id) active @endif">
                                <div>
                                    <div class="fw-semibold">{{ $ws->name }}</div>
                                    <div class="small opacity-75">{{ $ws->pivot->role ?? 'member' }}</div>
                                </div>
                                @if(($currentWorkspace->id ?? null) !== $ws->id)
                                    <form action="{{ route('workspace.switch') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="workspace_id" value="{{ $ws->id }}">
                                        <input type="hidden" name="redirect_to" value="{{ route('workspace.index') }}">
                                        <button type="submit" class="btn btn-sm btn-light">Usar</button>
                                    </form>
                                @else
                                    <span class="badge text-bg-light text-dark">Atual</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="bi bi-people me-2"></i>Membros do workspace</h3>
            </div>
            <div class="card-body">
                @if($currentWorkspace)
                    <div class="table-responsive mb-3">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>E-mail</th>
                                    <th>Papel</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($members as $member)
                                    <tr>
                                        <td>{{ $member->name }}</td>
                                        <td class="text-muted small">{{ $member->email }}</td>
                                        <td><span class="badge text-bg-secondary">{{ $member->pivot->role ?? 'member' }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($canInvite)
                        <hr>
                        <h6 class="mb-2">Convidar usuário</h6>
                        <p class="text-muted small">Adiciona um e-mail ao workspace atual. Usuários novos recebem link para definir senha (se o e-mail estiver configurado).</p>
                        <form action="{{ route('workspace.members.invite') }}" method="POST" class="row g-2">
                            @csrf
                            <div class="col-md-5">
                                <input type="email" name="email" class="form-control form-control-sm" placeholder="E-mail" required>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="name" class="form-control form-control-sm" placeholder="Nome (novos usuários)">
                            </div>
                            <div class="col-md-3">
                                <select name="role" class="form-select form-select-sm">
                                    <option value="member">Membro</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="send_reset_link" id="send-reset-link" value="1" checked>
                                    <label class="form-check-label small" for="send-reset-link">Enviar link para definir senha (novos)</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-sm">Convidar</button>
                            </div>
                        </form>
                    @else
                        <p class="text-muted small mb-0">Apenas owners/admins podem convidar membros.</p>
                    @endif
                @else
                    <p class="text-muted mb-0">Selecione um workspace para ver membros.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
