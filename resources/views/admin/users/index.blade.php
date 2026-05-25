@extends('layouts.adminlte')

@section('title', 'Gerenciamento de usuários')
@section('page_title', 'Usuários e planos')
@section('breadcrumb')
    <li class="breadcrumb-item active">Administração</li>
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="small-box text-bg-success">
            <div class="inner">
                <h3>{{ $stats['active'] }}</h3>
                <p>Acessos ativos</p>
            </div>
            <i class="small-box-icon bi bi-person-check"></i>
        </div>
    </div>
    <div class="col-md-4">
        <div class="small-box text-bg-warning">
            <div class="inner">
                <h3>{{ $stats['pending'] }}</h3>
                <p>Aguardando pagamento</p>
            </div>
            <i class="small-box-icon bi bi-hourglass-split"></i>
        </div>
    </div>
    <div class="col-md-4">
        <div class="small-box text-bg-danger">
            <div class="inner">
                <h3>{{ $stats['blocked'] }}</h3>
                <p>Bloqueados</p>
            </div>
            <i class="small-box-icon bi bi-person-lock"></i>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Usuários cadastrados</h3>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Cadastros novos ficam aguardando pagamento. Ao confirmar um pagamento ou liberar manualmente,
                    o acesso ao sistema é habilitado automaticamente.
                </p>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Usuário</th>
                                <th>Perfil</th>
                                <th>Status</th>
                                <th>Pagamento</th>
                                <th class="text-end">Gerenciar</th>
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
                                            <span class="badge text-bg-info mt-1">Interno sem cobrança de IA</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $u->subscriptionProfile?->name ?? 'Sem perfil' }}
                                        @if($u->subscriptionProfile)
                                            <div class="text-muted small">{{ $u->subscriptionProfile->monthlyPriceLabel() }}/mês</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $statusClass }}">{{ $u->accessStatusLabel() }}</span>
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
                                    <td>
                                        <form action="{{ route('admin.users.update-access', $u) }}" method="POST" class="row g-2 justify-content-end mb-2">
                                            @csrf
                                            @method('PATCH')
                                            <div class="col-md-4">
                                                <select name="subscription_profile_id" class="form-select form-select-sm">
                                                    <option value="">Sem perfil</option>
                                                    @foreach($profiles as $profile)
                                                        <option value="{{ $profile->id }}" @selected($u->subscription_profile_id === $profile->id)>
                                                            {{ $profile->name }} - {{ $profile->monthlyPriceLabel() }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <select name="access_status" class="form-select form-select-sm">
                                                    <option value="{{ \App\Models\User::ACCESS_PENDING_PAYMENT }}" @selected($u->access_status === \App\Models\User::ACCESS_PENDING_PAYMENT)>Aguardando pagamento</option>
                                                    <option value="{{ \App\Models\User::ACCESS_ACTIVE }}" @selected($u->access_status === \App\Models\User::ACCESS_ACTIVE)>Ativo</option>
                                                    <option value="{{ \App\Models\User::ACCESS_MANUAL_RELEASE }}" @selected($u->access_status === \App\Models\User::ACCESS_MANUAL_RELEASE)>Liberado manualmente</option>
                                                    <option value="{{ \App\Models\User::ACCESS_BLOCKED }}" @selected($u->access_status === \App\Models\User::ACCESS_BLOCKED)>Bloqueado</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <input type="date" name="access_expires_at" class="form-control form-control-sm" value="{{ $u->access_expires_at?->format('Y-m-d') }}">
                                            </div>
                                            <div class="col-md-2 d-flex gap-2 align-items-center justify-content-end">
                                                <div class="form-check small mb-0">
                                                    <input type="hidden" name="is_platform_internal" value="0">
                                                    <input class="form-check-input" type="checkbox" name="is_platform_internal" value="1" id="internal-{{ $u->id }}" @checked($u->is_platform_internal)>
                                                    <label class="form-check-label" for="internal-{{ $u->id }}">Interno</label>
                                                </div>
                                                <button class="btn btn-sm btn-primary">Salvar</button>
                                            </div>
                                        </form>

                                        <form action="{{ route('admin.users.confirm-payment', $u) }}" method="POST" class="row g-2 justify-content-end">
                                            @csrf
                                            <div class="col-md-4">
                                                <select name="subscription_profile_id" class="form-select form-select-sm" required>
                                                    @foreach($profiles as $profile)
                                                        <option value="{{ $profile->id }}" @selected($u->subscription_profile_id === $profile->id)>
                                                            {{ $profile->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <input type="number" name="months" class="form-control form-control-sm" value="1" min="1" max="24" aria-label="Meses">
                                            </div>
                                            <div class="col-md-3">
                                                <input type="date" name="paid_at" class="form-control form-control-sm" value="{{ now()->format('Y-m-d') }}">
                                            </div>
                                            <div class="col-md-3 text-end">
                                                <button class="btn btn-sm btn-outline-success" @disabled($profiles->isEmpty())>
                                                    Confirmar pagamento
                                                </button>
                                            </div>
                                        </form>
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
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">Novo perfil</h3>
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
                        <textarea name="description" class="form-control" rows="2"></textarea>
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

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Perfis existentes</h3>
            </div>
            <div class="card-body">
                @forelse($profiles as $profile)
                    <form action="{{ route('admin.subscription-profiles.update', $profile) }}" method="POST" class="border rounded p-3 mb-3">
                        @csrf
                        @method('PUT')
                        <div class="mb-2">
                            <input type="text" name="name" class="form-control form-control-sm" value="{{ $profile->name }}" required>
                        </div>
                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text">R$</span>
                            <input type="number" name="monthly_price" class="form-control" min="0" step="0.01" value="{{ number_format($profile->monthly_price_cents / 100, 2, '.', '') }}" required>
                        </div>
                        <textarea name="description" class="form-control form-control-sm mb-2" rows="2">{{ $profile->description }}</textarea>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="form-check small">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" name="is_active" id="profile-active-{{ $profile->id }}" value="1" @checked($profile->is_active)>
                                <label class="form-check-label" for="profile-active-{{ $profile->id }}">Ativo</label>
                            </div>
                            <button class="btn btn-sm btn-outline-primary">Atualizar</button>
                        </div>
                    </form>
                @empty
                    <p class="text-muted mb-0">Crie o primeiro perfil para definir o valor mensal.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
