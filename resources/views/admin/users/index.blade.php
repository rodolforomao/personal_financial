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

    .admin-users-profile-card {
        border: 1px solid var(--bs-border-color);
        border-radius: .9rem;
        padding: 1rem;
    }
</style>
@endpush

@section('content')
<div class="row g-3 mb-4">
    <div class="col-md-4">
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
    <div class="col-md-4">
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
    <div class="col-md-4">
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
                    <div class="text-muted small">
                        {{ $users->total() }} usuário(s) encontrados
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle admin-users-table">
                        <thead>
                            <tr>
                                <th>Usuário</th>
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
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $u->name }}</div>
                                        <div class="text-muted small">{{ $u->email }}</div>
                                        @if($u->is_platform_internal)
                                            <span class="badge rounded-pill text-bg-info mt-2">Interno sem cobrança de IA</span>
                                        @endif
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
                                    <td colspan="5" class="text-center text-muted">Nenhum usuário cadastrado.</td>
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
                            Crie e atualize os planos usados no cadastro e nas liberações de acesso.
                        </p>
                    </div>
                    <span class="badge rounded-pill text-bg-light align-self-start">{{ $profiles->count() }} perfil(is)</span>
                </div>

                <div class="row g-3">
                    <div class="col-xl-4">
                        <div class="card h-100 border-primary border-opacity-25">
                            <div class="card-header bg-primary bg-opacity-10 border-0">
                                <h4 class="h6 mb-0">Novo perfil</h4>
                            </div>
                            <div class="card-body">
                                <form action="{{ route('admin.subscription-profiles.store') }}" method="POST">
                                    @csrf
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
                                    <div class="form-check mb-3">
                                        <input type="hidden" name="is_active" value="0">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="new-profile-active" value="1" checked>
                                        <label class="form-check-label" for="new-profile-active">Ativo para novos cadastros</label>
                                    </div>
                                    <button class="btn btn-primary w-100">Criar perfil</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-8">
                        <div class="card h-100">
                            <div class="card-header bg-white">
                                <h4 class="h6 mb-0">Perfis existentes</h4>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    @forelse($profiles as $profile)
                                        <div class="col-lg-6">
                                            <form action="{{ route('admin.subscription-profiles.update', $profile) }}" method="POST" class="admin-users-profile-card h-100">
                                                @csrf
                                                @method('PUT')
                                                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                                    <div>
                                                        <div class="fw-semibold">{{ $profile->name }}</div>
                                                        <div class="text-muted small">{{ $profile->monthlyPriceLabel() }}/mês</div>
                                                    </div>
                                                    <span class="badge rounded-pill {{ $profile->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                                        {{ $profile->is_active ? 'Ativo' : 'Inativo' }}
                                                    </span>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label small mb-1">Nome</label>
                                                    <input type="text" name="name" class="form-control form-control-sm" value="{{ $profile->name }}" required>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label small mb-1">Valor mensal</label>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text">R$</span>
                                                        <input type="number" name="monthly_price" class="form-control" min="0" step="0.01" value="{{ number_format($profile->monthly_price_cents / 100, 2, '.', '') }}" required>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small mb-1">Descrição</label>
                                                    <textarea name="description" class="form-control form-control-sm" rows="3">{{ $profile->description }}</textarea>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div class="form-check small">
                                                        <input type="hidden" name="is_active" value="0">
                                                        <input class="form-check-input" type="checkbox" name="is_active" id="profile-active-{{ $profile->id }}" value="1" @checked($profile->is_active)>
                                                        <label class="form-check-label" for="profile-active-{{ $profile->id }}">Ativo</label>
                                                    </div>
                                                    <button class="btn btn-sm btn-outline-primary">Atualizar</button>
                                                </div>
                                            </form>
                                        </div>
                                    @empty
                                        <div class="col-12">
                                            <div class="text-center text-muted border rounded p-4">
                                                Crie o primeiro perfil para definir o valor mensal.
                                            </div>
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@foreach($users as $u)
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
@endsection
