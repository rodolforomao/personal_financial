@extends('layouts.adminlte')

@section('title', 'Gerenciamento de usuários')
@section('page_title', 'Usuários e planos')
@section('breadcrumb')
    <li class="breadcrumb-item active">Administração</li>
@endsection

@push('styles')
<style>
    .admin-users-stat-card {
        border: 0;
        border-radius: 1rem;
        box-shadow: 0 .5rem 1.5rem rgba(15, 23, 42, .08);
        overflow: hidden;
    }

    .admin-users-stat-card .stat-icon {
        align-items: center;
        background: rgba(255, 255, 255, .18);
        border-radius: 999px;
        display: inline-flex;
        height: 3rem;
        justify-content: center;
        width: 3rem;
    }

    .admin-users-tabs .nav-link {
        align-items: center;
        border-radius: 999px;
        color: var(--bs-secondary-color);
        display: inline-flex;
        font-weight: 600;
        gap: .5rem;
        padding: .65rem 1rem;
    }

    .admin-users-tabs .nav-link.active {
        background: var(--bs-primary);
        color: #fff;
        box-shadow: 0 .5rem 1rem rgba(var(--bs-primary-rgb), .2);
    }

    .admin-users-table > :not(caption) > * > * {
        padding-bottom: 1rem;
        padding-top: 1rem;
    }

    .admin-users-profile-description {
        max-width: 36rem;
    }

    .admin-users-profile-actions {
        min-width: 8rem;
    }

    .admin-users-diagnostic-card {
        background: var(--bs-tertiary-bg);
        border: 1px solid var(--bs-border-color);
        border-radius: .75rem;
        height: 100%;
        padding: .85rem;
    }

    .admin-users-diagnostic-label {
        color: var(--bs-secondary-color);
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .03em;
        text-transform: uppercase;
    }
</style>
@endpush

@section('content')
<div class="row g-3 mb-4">
    <div class="col-md-4 col-xl-2">
        <div class="card admin-users-stat-card text-bg-primary h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-white-50 small text-uppercase fw-semibold">Total</div>
                    <div class="display-6 fw-bold mb-0">{{ $stats['total'] }}</div>
                </div>
                <span class="stat-icon">
                    <i class="bi bi-people fs-4"></i>
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-xl-2">
        <div class="card admin-users-stat-card text-bg-success h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-white-50 small text-uppercase fw-semibold">Acessos ativos</div>
                    <div class="display-6 fw-bold mb-0">{{ $stats['active'] }}</div>
                </div>
                <span class="stat-icon">
                    <i class="bi bi-person-check fs-4"></i>
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-xl-2">
        <div class="card admin-users-stat-card text-bg-warning h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-black-50 small text-uppercase fw-semibold">Aguardando pagamento</div>
                    <div class="display-6 fw-bold mb-0">{{ $stats['pending'] }}</div>
                </div>
                <span class="stat-icon">
                    <i class="bi bi-hourglass-split fs-4"></i>
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-xl-2">
        <div class="card admin-users-stat-card text-bg-danger h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-white-50 small text-uppercase fw-semibold">Bloqueados</div>
                    <div class="display-6 fw-bold mb-0">{{ $stats['blocked'] }}</div>
                </div>
                <span class="stat-icon">
                    <i class="bi bi-person-lock fs-4"></i>
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-xl-2">
        <div class="card admin-users-stat-card text-bg-info h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-black-50 small text-uppercase fw-semibold">Internos</div>
                    <div class="display-6 fw-bold mb-0">{{ $stats['internal'] }}</div>
                </div>
                <span class="stat-icon">
                    <i class="bi bi-shield-check fs-4"></i>
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-xl-2">
        <div class="card admin-users-stat-card text-bg-secondary h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-white-50 small text-uppercase fw-semibold">Vencidos</div>
                    <div class="display-6 fw-bold mb-0">{{ $stats['expired'] }}</div>
                </div>
                <span class="stat-icon">
                    <i class="bi bi-calendar-x fs-4"></i>
                </span>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white border-bottom-0 pb-0">
        <ul class="nav nav-pills admin-users-tabs gap-2" id="admin-users-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="users-tab" data-bs-toggle="pill" data-bs-target="#users-pane" type="button" role="tab" aria-controls="users-pane" aria-selected="true">
                    <i class="bi bi-people"></i>
                    Usuários cadastrados
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="profiles-tab" data-bs-toggle="pill" data-bs-target="#profiles-pane" type="button" role="tab" aria-controls="profiles-pane" aria-selected="false">
                    <i class="bi bi-credit-card"></i>
                    Perfis e planos
                </button>
            </li>
        </ul>
    </div>

    <div class="card-body">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="users-pane" role="tabpanel" aria-labelledby="users-tab" tabindex="0">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
                    <div>
                        <h3 class="h5 mb-1">Usuários cadastrados</h3>
                        <p class="text-muted small mb-0">
                            Cadastros novos ficam aguardando pagamento. Ao confirmar um pagamento ou liberar manualmente,
                            o acesso ao sistema é habilitado automaticamente.
                        </p>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="text-muted small">{{ $users->total() }} usuário(s) encontrados</span>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#invite-user-modal">
                            <i class="bi bi-person-plus me-1"></i>
                            Convidar usuário
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle admin-users-table">
                        <thead>
                            <tr>
                                <th>Usuário</th>
                                <th>Dados</th>
                                <th>Perfil</th>
                                <th>Status</th>
                                <th>Pagamento</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $u)
                                @php
                                    $statusClass = match ($u->access_status) {
                                        \App\Models\User::ACCESS_ACTIVE, \App\Models\User::ACCESS_MANUAL_RELEASE => 'text-bg-success',
                                        \App\Models\User::ACCESS_BLOCKED => 'text-bg-danger',
                                        default => 'text-bg-warning',
                                    };
                                    $prefs = $u->preferences ?? [];
                                    $notificationPrefs = $prefs['notifications'] ?? [];
                                    $aiPrefs = $prefs['ai'] ?? [];
                                    $roles = $u->roles->pluck('name');
                                    $userWorkspaceLabels = $u->workspaces->map(fn ($workspace) => $workspace->name.' (#'.$workspace->id.', '.$workspace->pivot->role.')');
                                    $gmailConnection = $gmailConnections->get($u->id);
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $u->name }}</div>
                                        <div class="text-muted small">{{ $u->email }}</div>
                                        <div class="text-muted small">ID #{{ $u->id }}</div>
                                        @if($u->phone)
                                            <div class="text-muted small">{{ $u->phone }}</div>
                                        @endif
                                        @if($u->is_platform_internal)
                                            <span class="badge rounded-pill text-bg-info mt-2">Interno sem cobrança de IA</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="small">Cadastro: {{ $u->created_at?->format('d/m/Y H:i') ?? '—' }}</div>
                                        <div class="small text-muted">Atualizado: {{ $u->updated_at?->format('d/m/Y H:i') ?? '—' }}</div>
                                        <div class="small text-muted">Roles: {{ $roles->isNotEmpty() ? $roles->join(', ') : 'sem role' }}</div>
                                        <div class="small text-muted">Workspaces: {{ $u->workspaces->count() }}</div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $u->subscriptionProfile?->name ?? 'Sem perfil' }}</div>
                                        @if($u->subscriptionProfile)
                                            <div class="text-muted small">{{ $u->subscriptionProfile->monthlyPriceLabel() }}/mês</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill {{ $statusClass }}">{{ $u->accessStatusLabel() }}</span>
                                        @if($u->access_expires_at)
                                            <div class="text-muted small">Até {{ $u->access_expires_at->format('d/m/Y') }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($u->latestAccessPayment)
                                            <div>{{ $u->latestAccessPayment->amountLabel() }}</div>
                                            <div class="text-muted small">
                                                {{ $u->latestAccessPayment->paid_at?->format('d/m/Y') ?? 'Pendente' }}
                                            </div>
                                        @else
                                            <span class="text-muted small">Sem pagamento</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#manage-user-{{ $u->id }}">
                                            <i class="bi bi-sliders me-1"></i>
                                            Gerenciar
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Nenhum usuário cadastrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $users->links() }}
            </div>

            <div class="tab-pane fade" id="profiles-pane" role="tabpanel" aria-labelledby="profiles-tab" tabindex="0">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
                    <div>
                        <h3 class="h5 mb-1">Perfis e planos</h3>
                        <p class="text-muted small mb-0">
                            Gerencie os planos usados no cadastro e nas liberações de acesso sem ocupar espaço com formulários abertos.
                        </p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge rounded-pill text-bg-light">{{ $profiles->count() }} perfil(is)</span>
                        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#create-profile-modal">
                            <i class="bi bi-plus-lg me-1"></i>
                            Novo perfil
                        </button>
                    </div>
                </div>

                <div class="table-responsive border rounded">
                    <table class="table table-hover align-middle admin-users-table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Perfil</th>
                                <th>Valor mensal</th>
                                <th>Status</th>
                                <th>Descrição</th>
                                <th class="text-end admin-users-profile-actions">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($profiles as $profile)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $profile->name }}</div>
                                    </td>
                                    <td>{{ $profile->monthlyPriceLabel() }}/mês</td>
                                    <td>
                                        <span class="badge rounded-pill {{ $profile->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $profile->is_active ? 'Ativo' : 'Inativo' }}
                                        </span>
                                    </td>
                                    <td class="text-muted small admin-users-profile-description">
                                        {{ $profile->description ?: 'Sem descrição' }}
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#edit-profile-{{ $profile->id }}">
                                            <i class="bi bi-pencil-square me-1"></i>
                                            Editar
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        Crie o primeiro perfil para definir o valor mensal.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="invite-user-modal" tabindex="-1" aria-labelledby="invite-user-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('admin.users.invite') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="invite-user-label">Convidar usuário</h5>
                        <div class="text-muted small">Vincula ao workspace e opcionalmente libera acesso à plataforma.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">E-mail</label>
                        <input type="email" name="email" class="form-control" required maxlength="255" value="{{ old('email') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nome <span class="text-muted fw-normal">(novos usuários)</span></label>
                        <input type="text" name="name" class="form-control" maxlength="255" value="{{ old('name') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Workspace</label>
                        <select name="workspace_id" class="form-select" required>
                            <option value="">Selecione…</option>
                            @foreach($workspaces as $workspace)
                                <option value="{{ $workspace->id }}" @selected(old('workspace_id') == $workspace->id)>
                                    {{ $workspace->name }} (#{{ $workspace->id }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Papel no workspace</label>
                        <select name="role" class="form-select">
                            <option value="member">Membro</option>
                            <option value="admin">Admin</option>
                            <option value="owner">Owner</option>
                        </select>
                    </div>
                    <div class="form-check mb-2">
                        <input type="hidden" name="grant_platform_access" value="0">
                        <input class="form-check-input" type="checkbox" name="grant_platform_access" id="invite-grant-access" value="1" checked>
                        <label class="form-check-label" for="invite-grant-access">Liberar acesso à plataforma (1 ano)</label>
                    </div>
                    <div class="form-check">
                        <input type="hidden" name="send_reset_link" value="0">
                        <input class="form-check-input" type="checkbox" name="send_reset_link" id="invite-send-reset" value="1" checked>
                        <label class="form-check-label" for="invite-send-reset">Enviar link para definir senha (novos)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Enviar convite</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="create-profile-modal" tabindex="-1" aria-labelledby="create-profile-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('admin.subscription-profiles.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="create-profile-label">Novo perfil</h5>
                        <div class="text-muted small">Defina nome, preço e disponibilidade do plano.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" name="name" class="form-control" placeholder="Mensal, Premium..." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Valor mensal</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="number" name="monthly_price" class="form-control" value="20.00" min="0" step="0.01" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-check">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" id="new-profile-active" value="1" checked>
                        <label class="form-check-label" for="new-profile-active">Ativo para novos cadastros</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary">Criar perfil</button>
                </div>
            </form>
        </div>
    </div>
</div>

@foreach($profiles as $profile)
    <div class="modal fade" id="edit-profile-{{ $profile->id }}" tabindex="-1" aria-labelledby="edit-profile-label-{{ $profile->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('admin.subscription-profiles.update', $profile) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title" id="edit-profile-label-{{ $profile->id }}">Editar {{ $profile->name }}</h5>
                            <div class="text-muted small">{{ $profile->monthlyPriceLabel() }}/mês</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nome</label>
                            <input type="text" name="name" class="form-control" value="{{ $profile->name }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Valor mensal</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="number" name="monthly_price" class="form-control" min="0" step="0.01" value="{{ number_format($profile->monthly_price_cents / 100, 2, '.', '') }}" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea name="description" class="form-control" rows="3">{{ $profile->description }}</textarea>
                        </div>
                        <div class="form-check">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" name="is_active" id="profile-active-{{ $profile->id }}" value="1" @checked($profile->is_active)>
                            <label class="form-check-label" for="profile-active-{{ $profile->id }}">Ativo</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-primary">Salvar alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

@foreach($users as $u)
    @php
        $prefs = $u->preferences ?? [];
        $notificationPrefs = $prefs['notifications'] ?? [];
        $aiPrefs = $prefs['ai'] ?? [];
        $roles = $u->roles->pluck('name');
        $userWorkspaceLabels = $u->workspaces->map(fn ($workspace) => $workspace->name.' (#'.$workspace->id.', '.$workspace->pivot->role.')');
        $gmailConnection = $gmailConnections->get($u->id);
        $telegramLinked = !empty($notificationPrefs['telegram_chat_id']) || !empty($notificationPrefs['telegram_destination_display']);
        $whatsappLinked = !empty($notificationPrefs['whatsapp_phone']);
        $aiOwnKeySaved = collect($aiPrefs)->keys()->contains(fn ($key) => str_ends_with((string) $key, '_api_key_enc'));
    @endphp
    <div class="modal fade" id="manage-user-{{ $u->id }}" tabindex="-1" aria-labelledby="manage-user-label-{{ $u->id }}" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="manage-user-label-{{ $u->id }}">Gerenciar {{ $u->name }}</h5>
                        <div class="text-muted small">{{ $u->email }}</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                            <div>
                                <h6 class="mb-1">Diagnóstico do usuário</h6>
                                <p class="text-muted small mb-0">Resumo administrativo para validar cadastro, acesso, plano e integrações sem exibir tokens.</p>
                            </div>
                            <span class="badge rounded-pill {{ $u->hasActivePlatformAccess() ? 'text-bg-success' : 'text-bg-danger' }} align-self-start">
                                {{ $u->hasActivePlatformAccess() ? 'Acesso efetivo ativo' : 'Sem acesso efetivo' }}
                            </span>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="admin-users-diagnostic-card">
                                    <div class="admin-users-diagnostic-label mb-2">Conta</div>
                                    <div class="small"><strong>ID:</strong> #{{ $u->id }}</div>
                                    <div class="small"><strong>Nome:</strong> {{ $u->name }}</div>
                                    <div class="small"><strong>Email:</strong> {{ $u->email }}</div>
                                    <div class="small"><strong>Telefone:</strong> {{ $u->phone ?: 'não informado' }}</div>
                                    <div class="small"><strong>Email verificado:</strong> {{ $u->email_verified_at?->format('d/m/Y H:i') ?? 'não' }}</div>
                                    <div class="small"><strong>2FA:</strong> {{ $u->two_factor_enabled ? 'ativo' : 'inativo' }}</div>
                                    <div class="small"><strong>Criado em:</strong> {{ $u->created_at?->format('d/m/Y H:i') ?? '—' }}</div>
                                    <div class="small"><strong>Atualizado em:</strong> {{ $u->updated_at?->format('d/m/Y H:i') ?? '—' }}</div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="admin-users-diagnostic-card">
                                    <div class="admin-users-diagnostic-label mb-2">Acesso</div>
                                    <div class="small"><strong>Status:</strong> {{ $u->accessStatusLabel() }} <span class="text-muted">({{ $u->access_status }})</span></div>
                                    <div class="small"><strong>Perfil:</strong> {{ $u->subscriptionProfile?->name ?? 'sem perfil' }}</div>
                                    <div class="small"><strong>Interno:</strong> {{ $u->is_platform_internal ? 'sim' : 'não' }}</div>
                                    <div class="small"><strong>Aprovado em:</strong> {{ $u->access_approved_at?->format('d/m/Y H:i') ?? '—' }}</div>
                                    <div class="small"><strong>Aprovado por:</strong> {{ $u->accessApprovedBy?->name ?? '—' }}</div>
                                    <div class="small"><strong>Último pagamento:</strong> {{ $u->last_payment_at?->format('d/m/Y H:i') ?? '—' }}</div>
                                    <div class="small"><strong>Validade:</strong> {{ $u->access_expires_at?->format('d/m/Y H:i') ?? 'sem vencimento' }}</div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="admin-users-diagnostic-card">
                                    <div class="admin-users-diagnostic-label mb-2">Organização</div>
                                    <div class="small"><strong>Roles:</strong> {{ $roles->isNotEmpty() ? $roles->join(', ') : 'sem role' }}</div>
                                    <div class="small"><strong>Workspaces:</strong></div>
                                    @if($userWorkspaceLabels->isNotEmpty())
                                        <ul class="small mb-0 ps-3">
                                            @foreach($userWorkspaceLabels as $workspaceLabel)
                                                <li>{{ $workspaceLabel }}</li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <div class="small text-muted">Nenhum workspace vinculado.</div>
                                    @endif
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="admin-users-diagnostic-card">
                                    <div class="admin-users-diagnostic-label mb-2">Pagamento</div>
                                    <div class="small"><strong>Total de pagamentos:</strong> {{ $u->access_payments_count }}</div>
                                    @if($u->latestAccessPayment)
                                        <div class="small"><strong>Último valor:</strong> {{ $u->latestAccessPayment->amountLabel() }}</div>
                                        <div class="small"><strong>Status:</strong> {{ $u->latestAccessPayment->status }}</div>
                                        <div class="small"><strong>Provedor:</strong> {{ $u->latestAccessPayment->provider ?: '—' }}</div>
                                        <div class="small"><strong>Pago em:</strong> {{ $u->latestAccessPayment->paid_at?->format('d/m/Y H:i') ?? 'pendente' }}</div>
                                        <div class="small"><strong>Período:</strong>
                                            {{ $u->latestAccessPayment->billing_period_starts_at?->format('d/m/Y') ?? '—' }}
                                            até
                                            {{ $u->latestAccessPayment->billing_period_ends_at?->format('d/m/Y') ?? '—' }}
                                        </div>
                                    @else
                                        <div class="small text-muted">Nenhum pagamento registrado.</div>
                                    @endif
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="admin-users-diagnostic-card">
                                    <div class="admin-users-diagnostic-label mb-2">Integrações</div>
                                    <div class="small"><strong>Telegram:</strong> {{ $telegramLinked ? 'vinculado' : 'não vinculado' }}</div>
                                    <div class="small text-muted">Modo: {{ $notificationPrefs['telegram_mode'] ?? 'system' }} · Alertas: {{ !empty($notificationPrefs['notify_telegram']) ? 'sim' : 'não' }}</div>
                                    <div class="small text-muted">Destino: {{ $notificationPrefs['telegram_destination_display'] ?? $notificationPrefs['telegram_chat_id'] ?? '—' }}</div>
                                    <div class="small text-muted">Token próprio salvo: {{ !empty($notificationPrefs['telegram_bot_token_enc']) ? 'sim' : 'não' }}</div>
                                    <hr class="my-2">
                                    <div class="small"><strong>WhatsApp:</strong> {{ $whatsappLinked ? 'vinculado' : 'não vinculado' }}</div>
                                    <div class="small text-muted">Modo: {{ $notificationPrefs['whatsapp_mode'] ?? 'system' }} · Alertas: {{ !empty($notificationPrefs['notify_whatsapp']) ? 'sim' : 'não' }}</div>
                                    <div class="small text-muted">Número: {{ $notificationPrefs['whatsapp_phone'] ?? '—' }}</div>
                                    <div class="small text-muted">API própria salva: {{ !empty($notificationPrefs['whatsapp_api_token_enc']) ? 'sim' : 'não' }}</div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="admin-users-diagnostic-card">
                                    <div class="admin-users-diagnostic-label mb-2">IA e Gmail</div>
                                    <div class="small"><strong>IA:</strong> {{ $aiPrefs['mode'] ?? 'system' }}</div>
                                    <div class="small text-muted">Provider: {{ $aiPrefs['provider'] ?? config('financial.ai.default', 'openai') }} · Modelo: {{ $aiPrefs['model'] ?? 'padrão' }}</div>
                                    <div class="small text-muted">Aceite plataforma: {{ !empty($aiPrefs['platform_ai_accepted_at']) ? $aiPrefs['platform_ai_accepted_at'] : 'não' }}</div>
                                    <div class="small text-muted">Chave própria salva: {{ $aiOwnKeySaved ? 'sim' : 'não' }}</div>
                                    <hr class="my-2">
                                    <div class="small"><strong>Gmail:</strong> {{ $gmailConnection ? $gmailConnection->status : 'não conectado' }}</div>
                                    @if($gmailConnection)
                                        <div class="small text-muted">Email: {{ $gmailConnection->settings['email'] ?? $gmailConnection->credentials['email'] ?? '—' }}</div>
                                        <div class="small text-muted">Última sincronização: {{ $gmailConnection->last_sync_at?->format('d/m/Y H:i') ?? '—' }}</div>
                                        @if($gmailConnection->last_error)
                                            <div class="small text-danger">Erro: {{ \Illuminate\Support\Str::limit($gmailConnection->last_error, 120) }}</div>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <form action="{{ route('admin.users.update-access', $u) }}" method="POST" class="border rounded p-3 mb-3">
                        @csrf
                        @method('PATCH')
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <h6 class="mb-1">Acesso ao sistema</h6>
                                <p class="text-muted small mb-0">Altere perfil, status e validade do acesso.</p>
                            </div>
                            <button class="btn btn-sm btn-primary">Salvar acesso</button>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label">Perfil</label>
                                <select name="subscription_profile_id" class="form-select">
                                    <option value="">Sem perfil</option>
                                    @foreach($profiles as $profile)
                                        <option value="{{ $profile->id }}" @selected($u->subscription_profile_id === $profile->id)>
                                            {{ $profile->name }} - {{ $profile->monthlyPriceLabel() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select name="access_status" class="form-select">
                                    <option value="{{ \App\Models\User::ACCESS_PENDING_PAYMENT }}" @selected($u->access_status === \App\Models\User::ACCESS_PENDING_PAYMENT)>Aguardando pagamento</option>
                                    <option value="{{ \App\Models\User::ACCESS_ACTIVE }}" @selected($u->access_status === \App\Models\User::ACCESS_ACTIVE)>Ativo</option>
                                    <option value="{{ \App\Models\User::ACCESS_MANUAL_RELEASE }}" @selected($u->access_status === \App\Models\User::ACCESS_MANUAL_RELEASE)>Liberado manualmente</option>
                                    <option value="{{ \App\Models\User::ACCESS_BLOCKED }}" @selected($u->access_status === \App\Models\User::ACCESS_BLOCKED)>Bloqueado</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Validade</label>
                                <input type="date" name="access_expires_at" class="form-control" value="{{ $u->access_expires_at?->format('Y-m-d') }}">
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch mb-0">
                                    <input type="hidden" name="is_platform_internal" value="0">
                                    <input class="form-check-input" type="checkbox" name="is_platform_internal" value="1" id="internal-{{ $u->id }}" @checked($u->is_platform_internal)>
                                    <label class="form-check-label" for="internal-{{ $u->id }}">
                                        Usuário interno, sem cobrança de IA da plataforma
                                    </label>
                                </div>
                            </div>
                        </div>
                    </form>

                    <form action="{{ route('admin.users.confirm-payment', $u) }}" method="POST" class="border rounded p-3">
                        @csrf
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <h6 class="mb-1">Confirmar pagamento</h6>
                                <p class="text-muted small mb-0">Registra o pagamento manual e libera o período contratado.</p>
                            </div>
                            <button class="btn btn-sm btn-outline-success" @disabled($profiles->isEmpty())>
                                Confirmar pagamento
                            </button>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Perfil cobrado</label>
                                <select name="subscription_profile_id" class="form-select" required>
                                    @foreach($profiles as $profile)
                                        <option value="{{ $profile->id }}" @selected($u->subscription_profile_id === $profile->id)>
                                            {{ $profile->name }} - {{ $profile->monthlyPriceLabel() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Meses</label>
                                <input type="number" name="months" class="form-control" value="1" min="1" max="24">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Data do pagamento</label>
                                <input type="date" name="paid_at" class="form-control" value="{{ now()->format('Y-m-d') }}">
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endforeach

@if($errors->has('email') || old('workspace_id'))
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('invite-user-modal');
            if (modal) {
                bootstrap.Modal.getOrCreateInstance(modal).show();
            }
        });
    </script>
    @endpush
@endif
@endsection
