@extends('layouts.adminlte')

@section('title', 'Transações')
@section('page_title', 'Transações')
@section('breadcrumb')
    <li class="breadcrumb-item active">Transações</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <p class="text-muted mb-0">Selecione lançamentos para alterar categoria, operação, fonte, meio ou excluir em massa.</p>
    <div class="d-flex gap-2 flex-wrap">
        @if(($estornoPairCount ?? 0) > 0)
            <form action="{{ route('transactions.remove-estorno-pairs') }}" method="POST" class="mb-0"
                onsubmit="return confirm('Remover {{ $estornoTransactionCount }} lançamento(s) em {{ $estornoPairCount }} par(es)?');">
                @csrf
                <button type="submit" class="btn btn-outline-dark btn-sm">
                    <i class="bi bi-x-circle"></i> Remover estornos ({{ $estornoPairCount }})
                </button>
            </form>
        @endif
        <a href="{{ route('documents.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-file-earmark-image"></i> Documentos
        </a>
        <a href="{{ route('transactions.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> Nova transação
        </a>
    </div>
</div>

@if(($uncategorizedCount ?? 0) > 0)
<div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <span><strong>{{ $uncategorizedCount }}</strong> sem categoria no workspace.</span>
    <a href="{{ route('transactions.index', ['missing' => 'category']) }}" class="btn btn-warning btn-sm">Filtrar e editar em massa</a>
</div>
@endif

@if(request('statement_import_id'))
<div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <span>Mostrando transações criadas pela importação de extrato #{{ request('statement_import_id') }}. Selecione as linhas para alterar propriedades em massa.</span>
    <a href="{{ route('statements.reconcile', request('statement_import_id')) }}" class="btn btn-outline-primary btn-sm">Ver conciliação</a>
</div>
@endif

<div class="card mb-3">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0 small"><i class="bi bi-funnel"></i> Filtros</h3>
        @if($filtersActive ?? false)
            <span class="badge text-bg-primary">Filtros ativos</span>
        @endif
    </div>
    <div class="card-body py-2">
        <form method="GET" id="tx-filter-form">
            @if(request('statement_import_id'))
                <input type="hidden" name="statement_import_id" value="{{ request('statement_import_id') }}">
            @endif
            <div class="row g-2 align-items-end">
                <div class="col-md-4 col-lg-3">
                    <label class="form-label small mb-0">Descrição contém</label>
                    <input type="text" name="description" class="form-control form-control-sm"
                           value="{{ request('description') }}" placeholder="Ex.: uber, aporte…">
                </div>
                <div class="col-md-4 col-lg-2">
                    <label class="form-label small mb-0">Contraparte contém</label>
                    <input type="text" name="counterparty" class="form-control form-control-sm"
                           value="{{ request('counterparty') }}" placeholder="Nome do favorecido">
                </div>
                <div class="col-md-2 col-lg-2">
                    <label class="form-label small mb-0">Data de</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2 col-lg-2">
                    <label class="form-label small mb-0">Data até</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-4 col-lg-3">
                    <label class="form-label small mb-0">Categoria</label>
                    <select name="category_id" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-lg-3">
                    <label class="form-label small mb-0">Empresa</label>
                    <select name="company_id" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        @foreach($companies as $co)
                            <option value="{{ $co->id }}" @selected(request('company_id') == $co->id)>{{ $co->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-lg-3">
                    <label class="form-label small mb-0">Operação</label>
                    <select name="operation_id" id="filter-operation-id" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        @foreach($operations as $op)
                            <option value="{{ $op->id }}" @selected(request('operation_id') == $op->id)>{{ $op->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-lg-3">
                    <label class="form-label small mb-0">Unidade (apto)</label>
                    <select name="operation_unit_id" id="filter-operation-unit-id" class="form-select form-select-sm"
                            @disabled(!request('operation_id'))>
                        <option value="">Todas</option>
                        @foreach($operationUnits as $unit)
                            <option value="{{ $unit->id }}" @selected(request('operation_unit_id') == $unit->id)>
                                {{ $unit->displayName() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-lg-2">
                    <label class="form-label small mb-0">Tipo</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="expense" @selected(request('type') === 'expense')>Despesa</option>
                        <option value="income" @selected(request('type') === 'income')>Receita</option>
                        <option value="transfer" @selected(request('type') === 'transfer')>Transferência</option>
                    </select>
                </div>
                <div class="col-md-2 col-lg-2">
                    <label class="form-label small mb-0">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        @foreach(\App\Core\Enums\TransactionStatus::cases() as $st)
                            <option value="{{ $st->value }}" @selected(request('status') === $st->value)>{{ $st->value }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-lg-2">
                    <label class="form-label small mb-0">Fonte</label>
                    <select name="funding_source" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        @foreach(\App\Core\Enums\FundingSource::cases() as $fs)
                            <option value="{{ $fs->value }}" @selected(request('funding_source') === $fs->value)>{{ $fs->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-lg-2">
                    <label class="form-label small mb-0">Meio de pagamento</label>
                    <select name="payment_method" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        @foreach(\App\Core\Enums\PaymentMethod::cases() as $pm)
                            <option value="{{ $pm->value }}" @selected(request('payment_method') === $pm->value)>{{ $pm->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-lg-2">
                    <label class="form-label small mb-0">Valor mín.</label>
                    <input type="number" name="amount_min" class="form-control form-control-sm" step="0.01" min="0"
                           value="{{ request('amount_min') }}" placeholder="0">
                </div>
                <div class="col-md-2 col-lg-2">
                    <label class="form-label small mb-0">Valor máx.</label>
                    <input type="number" name="amount_max" class="form-control form-control-sm" step="0.01" min="0"
                           value="{{ request('amount_max') }}" placeholder="">
                </div>
                @if(count($knownSources ?? []) > 0)
                <div class="col-md-2 col-lg-2">
                    <label class="form-label small mb-0">Origem</label>
                    <select name="source" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        @foreach($knownSources as $src)
                            <option value="{{ $src }}" @selected(request('source') === $src)>{{ $src }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="col-md-3 col-lg-3">
                    <label class="form-label small mb-0">Campos em branco</label>
                    <select name="missing" class="form-select form-select-sm">
                        <option value="">—</option>
                        <option value="category" @selected(request('missing') === 'category')>Sem categoria ({{ $missingCounts['category'] ?? 0 }})</option>
                        <option value="operation" @selected(request('missing') === 'operation')>Sem operação ({{ $missingCounts['operation'] ?? 0 }})</option>
                        <option value="company" @selected(request('missing') === 'company')>Sem empresa ({{ $missingCounts['company'] ?? 0 }})</option>
                        <option value="funding_source" @selected(request('missing') === 'funding_source')>Sem fonte ({{ $missingCounts['funding_source'] ?? 0 }})</option>
                        <option value="payment_method" @selected(request('missing') === 'payment_method')>Sem meio ({{ $missingCounts['payment_method'] ?? 0 }})</option>
                    </select>
                </div>
                <div class="col-md-2 col-lg-1">
                    <label class="form-label small mb-0">Por página</label>
                    <select name="per_page" class="form-select form-select-sm">
                        @foreach([25, 50, 100] as $n)
                            <option value="{{ $n }}" @selected((int) request('per_page', 25) === $n)>{{ $n }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-lg-3 d-flex gap-1 align-items-end">
                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filtrar</button>
                    <a href="{{ route('transactions.index') }}" class="btn btn-sm btn-outline-secondary">Limpar</a>
                </div>
            </div>
        </form>
    </div>
</div>

@include('finance.transactions._bulk_actions', [
    'categories' => $categories,
    'companies' => $companies,
    'operations' => $operations,
])

<div class="card">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h3 class="card-title mb-0">Lançamentos</h3>
        <div class="card-tools d-flex gap-1">
            <form action="{{ route('transactions.bulk-categorize') }}" method="POST" class="mb-0 d-inline"
                onsubmit="return confirm('Categorizar automaticamente todas sem categoria?');">
                @csrf
                <button type="submit" class="btn btn-warning btn-sm" @disabled(($uncategorizedCount ?? 0) === 0)>
                    <i class="bi bi-magic"></i> Auto-categorizar
                </button>
            </form>
            <a href="{{ route('categorization-rules.index') }}" class="btn btn-outline-secondary btn-sm">Regras</a>
        </div>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th style="width:36px">
                        <input type="checkbox" class="form-check-input" id="tx-check-all" title="Selecionar página">
                    </th>
                    <th>Data</th>
                    <th>Descrição</th>
                    <th>Categoria</th>
                    <th>Empresa</th>
                    <th>Operação</th>
                    <th>Fonte / Meio</th>
                    <th>Tipo</th>
                    <th class="text-end">Valor</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $tx)
                    <tr>
                        <td>
                            <input type="checkbox" class="form-check-input tx-bulk-check" value="{{ $tx->id }}">
                        </td>
                        <td>{{ $tx->transaction_date->format('d/m/Y') }}</td>
                        <td>
                            {{ $tx->description }}
                            @if(($tx->documents_count ?? 0) > 0)
                                <span class="badge text-bg-info ms-1"><i class="bi bi-paperclip"></i> {{ $tx->documents_count }}</span>
                            @endif
                            @if($tx->linkedTransaction)
                                <a href="{{ route('transactions.edit', $tx->linkedTransaction) }}"
                                   class="badge text-bg-secondary ms-1 text-decoration-none"
                                   title="{{ $tx->operation_id ? 'Saída espelhada no capital pessoal' : 'Entrada espelhada na operação' }}: #{{ $tx->linkedTransaction->id }} · {{ $tx->linkedTransaction->operation?->name ?? 'capital pessoal' }}">
                                    <i class="bi bi-arrow-left-right"></i>
                                    {{ $tx->operation_id ? 'capital pessoal' : ($tx->linkedTransaction->operation?->name ?? '—') }}
                                </a>
                            @endif
                            @if($tx->counterparty)<br><small class="text-muted">{{ $tx->counterparty }}</small>@endif
                        </td>
                        <td>
                            @if($tx->category)
                                <span class="badge text-bg-secondary">{{ $tx->category->name }}</span>
                            @else
                                <span class="text-warning">Sem categoria</span>
                            @endif
                        </td>
                        <td>{{ $tx->company?->name ?? '—' }}</td>
                        <td class="small">
                            @if($tx->operation)
                                {{ $tx->operation->name }}
                                @if($tx->operationUnit)<br><span class="text-muted">{{ $tx->operationUnit->displayName() }}</span>@endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="small">
                            @if($tx->fundingSourceLabel())
                                <span class="badge text-bg-light text-dark border">{{ $tx->fundingSourceLabel() }}</span>
                            @endif
                            @if($tx->paymentMethodLabel())
                                <span class="badge text-bg-secondary">{{ $tx->paymentMethodLabel() }}</span>
                            @endif
                            @if(!$tx->fundingSourceLabel() && !$tx->paymentMethodLabel())
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td><span class="badge text-bg-{{ $tx->type->value === 'income' ? 'success' : 'danger' }}">{{ $tx->type->value }}</span></td>
                        <td class="text-end fw-semibold">R$ {{ number_format($tx->amount, 2, ',', '.') }}</td>
                        <td class="text-end">
                            <a href="{{ route('transactions.edit', $tx) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-muted py-4">Nenhum lançamento com estes filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($transactions->hasPages())
        <div class="card-footer">{{ $transactions->links() }}</div>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    const opSelect = document.getElementById('filter-operation-id');
    const unitSelect = document.getElementById('filter-operation-unit-id');
    if (!opSelect || !unitSelect) return;

    const unitsByOperation = @json(
        $operations->mapWithKeys(fn ($op) => [
            $op->id => $op->activeUnits->map(fn ($u) => ['id' => $u->id, 'name' => $u->displayName()])->values(),
        ])
    );

    const selectedUnit = @json(request('operation_unit_id'));

    function refreshUnits() {
        const opId = opSelect.value;
        unitSelect.innerHTML = '<option value="">Todas</option>';
        unitSelect.disabled = !opId;
        if (!opId || !unitsByOperation[opId]) return;

        unitsByOperation[opId].forEach((u) => {
            const opt = document.createElement('option');
            opt.value = u.id;
            opt.textContent = u.name;
            if (String(selectedUnit) === String(u.id)) opt.selected = true;
            unitSelect.appendChild(opt);
        });
    }

    opSelect.addEventListener('change', () => {
        unitSelect.value = '';
        refreshUnits();
    });
})();
</script>
@endpush
