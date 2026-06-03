@extends('layouts.adminlte')

@section('title', 'Saneamento de dados')
@section('page_title', 'Saneamento de dados')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Saneamento</li>
@endsection

@section('content')
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link @if($tab === 'overview') active @endif" href="{{ route('data-hygiene.index', ['tab' => 'overview']) }}">Visão geral</a>
    </li>
    <li class="nav-item">
        <a class="nav-link @if($tab === 'without_operation') active @endif" href="{{ route('data-hygiene.index', ['tab' => 'without_operation']) }}">
            Sem operação <span class="badge text-bg-secondary">{{ $audit['without_operation'] }}</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link @if($tab === 'missing_unit') active @endif" href="{{ route('data-hygiene.index', ['tab' => 'missing_unit']) }}">
            Sem unidade <span class="badge text-bg-warning">{{ $audit['without_unit_in_ops_with_units'] }}</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link @if($tab === 'duplicates') active @endif" href="{{ route('data-hygiene.index', ['tab' => 'duplicates']) }}">
            Possíveis duplicatas
            @if($audit['duplicate_groups'] > 0)
                <span class="badge text-bg-danger">{{ $audit['duplicate_groups'] }}</span>
            @endif
        </a>
    </li>
</ul>

@if($tab === 'overview')
<div class="row mb-3">
    <div class="col-md-4">
        <div class="small-box text-bg-info">
            <div class="inner">
                <h3>{{ $audit['without_operation'] }}</h3>
                <p>Sem operação</p>
            </div>
            <a href="{{ route('data-hygiene.index', ['tab' => 'without_operation']) }}" class="small-box-footer">Revisar <i class="bi bi-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-md-4">
        <div class="small-box text-bg-warning">
            <div class="inner">
                <h3>{{ $audit['without_unit_in_ops_with_units'] }}</h3>
                <p>Operação com unidades, lançamento sem unidade</p>
            </div>
            <a href="{{ route('data-hygiene.index', ['tab' => 'missing_unit']) }}" class="small-box-footer">Corrigir em lote <i class="bi bi-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-md-4">
        <div class="small-box text-bg-secondary">
            <div class="inner">
                <h3>{{ count($audit['wrong_company_types']) }}</h3>
                <p>Tipos de empresa a revisar</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="small-box {{ $audit['duplicate_groups'] > 0 ? 'text-bg-danger' : 'text-bg-secondary' }}">
            <div class="inner">
                <h3>{{ $audit['duplicate_groups'] }}</h3>
                <p>Possíveis duplicatas</p>
            </div>
            @if($audit['duplicate_groups'] > 0)
                <a href="{{ route('data-hygiene.index', ['tab' => 'duplicates']) }}" class="small-box-footer">Revisar <i class="bi bi-arrow-circle-right"></i></a>
            @endif
        </div>
    </div>
</div>

<div class="card card-outline card-primary mb-3">
    <div class="card-header"><h3 class="card-title mb-0">Correções rápidas</h3></div>
    <div class="card-body">
        <div class="row g-3">
            @if(count($audit['wrong_company_types']) > 0)
            <div class="col-md-6">
                <div class="border rounded p-3 h-100">
                    <h6 class="fw-semibold">Tipos de empresa</h6>
                    <ul class="small mb-2">
                        @foreach($audit['wrong_company_types'] as $issue)
                            <li><strong>{{ $issue['name'] }}</strong>: {{ $issue['current'] }} → {{ $issue['suggested'] }}</li>
                        @endforeach
                    </ul>
                    <form action="{{ route('data-hygiene.fix') }}" method="POST">
                        @csrf
                        <input type="hidden" name="action" value="fix_company_types">
                        <button type="submit" class="btn btn-sm btn-primary">Aplicar correção</button>
                    </form>
                </div>
            </div>
            @endif

            @if($audit['geral_operation'])
            <div class="col-md-6">
                <div class="border rounded p-3 h-100">
                    <h6 class="fw-semibold">Operação “{{ $audit['geral_operation']['name'] }}”</h6>
                    <p class="small text-muted mb-2">
                        {{ $audit['geral_operation']['transaction_count'] }} lançamento(s).
                        @if($audit['geral_operation']['exclude_from_main_dashboard'])
                            Hoje <strong>não</strong> entra no dashboard consolidado.
                        @else
                            Já visível no consolidado.
                        @endif
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        @if($audit['geral_operation']['exclude_from_main_dashboard'])
                        <form action="{{ route('data-hygiene.fix') }}" method="POST">
                            @csrf
                            <input type="hidden" name="action" value="show_geral_on_dashboard">
                            <button type="submit" class="btn btn-sm btn-outline-primary">Mostrar no dashboard consolidado</button>
                        </form>
                        @endif
                        <form action="{{ route('data-hygiene.fix') }}" method="POST" onsubmit="return confirm('Mover todos os lançamentos da operação Geral para escopo pessoal (sem operação)?');">
                            @csrf
                            <input type="hidden" name="action" value="release_geral_transactions">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Liberar para pessoal (sem operação)</button>
                        </form>
                    </div>
                    <p class="small text-muted mt-2 mb-0">Use “liberar” se salário e gastos pessoais estiverem presos na operação Geral.</p>
                </div>
            </div>
            @endif
        </div>

        @if(count($audit['wrong_company_types']) === 0 && ! $audit['geral_operation'])
            <p class="text-muted mb-0">Nenhuma correção automática pendente. Revise as abas de lançamentos.</p>
        @endif
    </div>
</div>

@if($unitWarnings->isNotEmpty())
<div class="card card-outline card-warning mb-3">
    <div class="card-header"><h3 class="card-title mb-0">Unidades que parecem empresas</h3></div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead><tr><th>Operação</th><th>Unidade</th><th></th></tr></thead>
            <tbody>
                @foreach($unitWarnings as $w)
                    <tr>
                        <td>{{ $w['operation'] }}</td>
                        <td>{{ $w['unit'] }}</td>
                        <td class="text-end">
                            <a href="{{ route('operations.index') }}" class="btn btn-xs btn-outline-secondary btn-sm">Operações</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted">
        Use unidades para subdivisões da operação (aptos, lojas, salas, etc.) — empresas (sócios, clientes) ficam em <a href="{{ route('companies.index') }}">Empresas</a>.
    </div>
</div>
@endif

<div class="card">
    <div class="card-header"><h3 class="card-title mb-0">Operações com unidades</h3></div>
    <ul class="list-group list-group-flush">
        @forelse($audit['operations_with_units'] as $op)
            <li class="list-group-item d-flex justify-content-between">
                <span>{{ $op['name'] }} ({{ $op['units_count'] }} unidades)</span>
                <a href="{{ route('operations.show', $op['id']) }}" class="btn btn-sm btn-outline-primary">Painel</a>
            </li>
        @empty
            <li class="list-group-item text-muted">Nenhuma operação com unidades cadastradas.</li>
        @endforelse
    </ul>
</div>
@endif

@if($tab === 'without_operation')
<div class="alert alert-light border mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
    <span>Lançamentos sem operação entram no <strong>dashboard consolidado</strong> (salário, gastos pessoais, lucros repassados).
    <a href="{{ route('transactions.index') }}?{{ http_build_query(['missing' => 'operation']) }}">Ver na lista de transações</a></span>
    <form action="{{ route('data-hygiene.auto-assign-operations') }}" method="POST" class="mb-0"
          onsubmit="return confirm('Vincular operações automaticamente via empresa e descrição?');">
        @csrf
        <button type="submit" class="btn btn-warning btn-sm">
            <i class="bi bi-magic"></i> Vincular operações automaticamente
        </button>
    </form>
</div>

<div class="card mb-3">
    <div class="card-header py-2 d-flex justify-content-between align-items-center filter-card-header @if(!($woFiltersActive ?? false)) collapsed @endif"
         data-bs-toggle="collapse" data-bs-target="#wo-filter-collapse"
         aria-expanded="{{ ($woFiltersActive ?? false) ? 'true' : 'false' }}" aria-controls="wo-filter-collapse">
        <h3 class="card-title mb-0 small">
            <i class="bi bi-funnel"></i> Filtros
            <i class="bi bi-chevron-down ms-1 filter-chevron" style="font-size:.75rem"></i>
        </h3>
        @if($woFiltersActive ?? false)
            <span class="badge text-bg-primary">Filtros ativos</span>
        @endif
    </div>
    <div class="collapse @if($woFiltersActive ?? false) show @endif" id="wo-filter-collapse">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('data-hygiene.index') }}" id="wo-filter-form">
                <input type="hidden" name="tab" value="without_operation">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4 col-lg-3">
                        <label class="form-label small mb-0">Descrição contém</label>
                        <input type="text" name="description" class="form-control form-control-sm"
                               value="{{ $woFilters['description'] ?? '' }}" placeholder="Ex.: pagamento, uber…">
                    </div>
                    <div class="col-md-3 col-lg-2">
                        <label class="form-label small mb-0">Tipo</label>
                        <select name="type" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <option value="income"   @selected(($woFilters['type'] ?? '') === 'income')>Receita</option>
                            <option value="expense"  @selected(($woFilters['type'] ?? '') === 'expense')>Despesa</option>
                            <option value="transfer" @selected(($woFilters['type'] ?? '') === 'transfer')>Transferência</option>
                        </select>
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <label class="form-label small mb-0">Empresa</label>
                        <select name="company_id" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            @foreach($companies ?? [] as $co)
                                <option value="{{ $co->id }}" @selected(($woFilters['company_id'] ?? '') == $co->id)>{{ $co->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <label class="form-label small mb-0">Categoria</label>
                        <select name="category_id" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            @foreach($categories ?? [] as $cat)
                                <option value="{{ $cat->id }}" @selected(($woFilters['category_id'] ?? '') == $cat->id)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 col-lg-2">
                        <label class="form-label small mb-0">Data de</label>
                        <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $woFilters['date_from'] ?? '' }}">
                    </div>
                    <div class="col-md-2 col-lg-2">
                        <label class="form-label small mb-0">Data até</label>
                        <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $woFilters['date_to'] ?? '' }}">
                    </div>
                    <div class="col-md-3 col-lg-2 d-flex gap-1 align-items-end">
                        <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filtrar</button>
                        <a href="{{ route('data-hygiene.index', ['tab' => 'without_operation']) }}" class="btn btn-sm btn-outline-secondary">Limpar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@include('finance.data-hygiene._transaction_table', [
    'transactions' => $withoutOperation,
    'emptyMessage' => 'Nenhum lançamento sem operação.',
    'showOperation' => false,
])
@endif

@if($tab === 'missing_unit')
<div class="alert alert-light border mb-3">
    Selecione os lançamentos, escolha operação e unidade, e aplique em lote. Opcional — você pode deixar sem unidade.
</div>

<form action="{{ route('data-hygiene.bulk-assign') }}" method="POST" id="hygiene-bulk-form">
    @csrf
    <input type="hidden" name="tab" value="missing_unit">
    <div class="card card-outline card-primary mb-3">
        <div class="card-body row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Operação</label>
                <select name="operation_id" id="hygiene-operation-id" class="form-select" required>
                    <option value="">—</option>
                    @foreach($operations as $op)
                        @if($op->activeUnits->isNotEmpty())
                            <option value="{{ $op->id }}">{{ $op->name }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Unidade</label>
                <select name="operation_unit_id" id="hygiene-unit-id" class="form-select">
                    <option value="">— Só operação —</option>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100" id="hygiene-bulk-submit" disabled>Aplicar nos selecionados</button>
            </div>
        </div>
    </div>

    @include('finance.data-hygiene._transaction_table', [
        'transactions' => $missingUnit,
        'emptyMessage' => 'Nenhum lançamento pendente de unidade.',
        'showOperation' => true,
        'withCheckboxes' => true,
    ])
</form>

@push('scripts')
<script>
(function () {
    const unitsByOp = @json($operations->mapWithKeys(fn ($op) => [
        $op->id => $op->activeUnits->map(fn ($u) => ['id' => $u->id, 'label' => $u->displayName()])->values(),
    ]));
    const opSel = document.getElementById('hygiene-operation-id');
    const unitSel = document.getElementById('hygiene-unit-id');
    const submitBtn = document.getElementById('hygiene-bulk-submit');
    const checkboxes = () => document.querySelectorAll('.hygiene-tx-checkbox:checked');

    function refreshUnits() {
        const opId = opSel.value;
        unitSel.innerHTML = '<option value="">— Só operação —</option>';
        (unitsByOp[opId] || []).forEach(u => {
            const o = document.createElement('option');
            o.value = u.id;
            o.textContent = u.label;
            unitSel.appendChild(o);
        });
        updateSubmit();
    }

    function updateSubmit() {
        submitBtn.disabled = checkboxes().length === 0 || !opSel.value;
    }

    opSel?.addEventListener('change', refreshUnits);
    document.getElementById('hygiene-bulk-form')?.addEventListener('change', updateSubmit);
    document.querySelectorAll('.hygiene-tx-checkbox, #hygiene-select-all')?.forEach(el => el.addEventListener('change', updateSubmit));
    document.getElementById('hygiene-select-all')?.addEventListener('change', function () {
        document.querySelectorAll('.hygiene-tx-checkbox').forEach(cb => { cb.checked = this.checked; });
        updateSubmit();
    });
})();
</script>
@endpush
@endif

@if($tab === 'duplicates')

@if($duplicateGroups->isEmpty())
    <div class="alert alert-success d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill fs-5 flex-shrink-0"></i>
        <div>Nenhum lançamento possivelmente duplicado encontrado. Base de dados limpa.</div>
    </div>
@else
    <div class="alert alert-light border mb-3 small">
        <i class="bi bi-info-circle"></i>
        Exibindo até 60 grupos. Lançamentos com <strong>mesmo valor e datas próximas</strong> são listados para revisão.
        Use <strong>"Não é duplicata"</strong> para ocultar falsos positivos.
        Para excluir um lançamento, abra-o pelo botão <strong>Editar</strong>.
    </div>

    @foreach($duplicateGroups as $group)
    @php
        $txList = $group['transactions'];
        $ids    = $txList->pluck('id')->sort()->values();
        $badgeColor = match($group['confidence']) {
            'high'   => 'danger',
            'medium' => 'warning',
            default  => 'secondary',
        };
        $badgeLabel = match($group['confidence']) {
            'high'   => 'Alta confiança',
            'medium' => 'Média confiança',
            default  => 'Baixa confiança',
        };
    @endphp
    <div class="card card-outline card-{{ $badgeColor === 'danger' ? 'danger' : ($badgeColor === 'warning' ? 'warning' : 'secondary') }} mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <span class="badge text-bg-{{ $badgeColor }} me-2">{{ $badgeLabel }}</span>
                <span class="small">{{ $group['reason'] }}</span>
            </div>
            <form method="POST" action="{{ route('data-hygiene.dismiss-duplicate') }}">
                @csrf
                <input type="hidden" name="id1" value="{{ $ids[0] }}">
                <input type="hidden" name="id2" value="{{ $ids[1] }}">
                <button type="submit" class="btn btn-sm btn-outline-secondary"
                        title="Ocultar este par — não é duplicata">
                    <i class="bi bi-x-lg"></i> Não é duplicata
                </button>
            </form>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Data</th>
                        <th>Descrição</th>
                        <th>Contraparte</th>
                        <th>Empresa</th>
                        <th>Categoria</th>
                        <th>Tipo</th>
                        <th class="text-end">Valor</th>
                        <th>Status</th>
                        <th>Origem</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($txList as $tx)
                    <tr>
                        <td class="text-nowrap">{{ $tx->transaction_date->format('d/m/Y') }}</td>
                        <td>{{ Str::limit($tx->description, 45) }}</td>
                        <td class="text-muted small">{{ $tx->counterparty ? Str::limit($tx->counterparty, 25) : '—' }}</td>
                        <td class="small">{{ $tx->company?->name ?? '—' }}</td>
                        <td class="small">{{ $tx->category?->name ?? '—' }}</td>
                        <td>
                            <span class="badge text-bg-{{ $tx->type->value === 'income' ? 'success' : ($tx->type->value === 'expense' ? 'danger' : 'secondary') }}">
                                {{ match($tx->type->value) { 'income' => 'Receita', 'expense' => 'Despesa', default => 'Transfer.' } }}
                            </span>
                        </td>
                        <td class="text-end text-nowrap">R$ {{ number_format($tx->amount, 2, ',', '.') }}</td>
                        <td class="small">
                            @php $st = $tx->status->value ?? $tx->status; @endphp
                            <span class="badge text-bg-{{ $st === 'confirmed' ? 'success' : ($st === 'pending' ? 'warning' : 'secondary') }}">
                                {{ match($st) { 'confirmed' => 'Confirmado', 'pending' => 'Pendente', 'cancelled' => 'Cancelado', 'reconciled' => 'Conciliado', default => $st } }}
                            </span>
                        </td>
                        <td class="small text-muted">{{ $tx->source !== 'manual' ? $tx->source : '' }}</td>
                        <td class="text-end">
                            <a href="{{ route('transactions.edit', $tx) }}" class="btn btn-xs btn-outline-secondary btn-sm">
                                Editar
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endforeach
@endif

@endif

@endsection
