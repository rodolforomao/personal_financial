@extends('layouts.adminlte')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard CFO')
@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
<style>
    .sensitive-value {
        font-variant-numeric: tabular-nums;
    }

    .sensitive-visual {
        filter: blur(8px);
        opacity: .55;
        pointer-events: none;
        transition: filter .18s ease, opacity .18s ease;
    }

    body.dashboard-values-visible .sensitive-visual {
        filter: none;
        opacity: 1;
        pointer-events: auto;
    }
</style>
@endpush

@section('content')
@php
    $excludedIds = $dashboardFilter->normalizedExcludeIds();
    $moneyMask = 'R$ •••••';
    $sensitiveMoney = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
@endphp

<div class="d-flex justify-content-end mb-3">
    <button type="button"
            class="btn btn-outline-secondary btn-sm"
            id="toggle-sensitive-values"
            aria-pressed="false"
            aria-label="Mostrar valores sensíveis">
        <i class="bi bi-eye"></i>
        <span>Mostrar valores</span>
    </button>
</div>

<div class="card card-outline card-secondary mb-3">
    <div class="card-header py-2">
        <h3 class="card-title mb-0"><i class="bi bi-funnel"></i> Escopo do painel</h3>
    </div>
    <div class="card-body py-3">
        <form action="{{ route('dashboard.filter') }}" method="POST" id="dashboard-filter-form">
            @csrf
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <div class="form-check form-switch mb-0">
                        <input type="hidden" name="include_all_operations" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="include_all_operations" value="1"
                               id="include-all-operations" @checked($dashboardFilter->includeAllOperations)>
                        <label class="form-check-label" for="include-all-operations">
                            Incluir <strong>todas</strong> as operações
                        </label>
                    </div>
                    <small class="text-muted d-block mt-1">
                        Desligado: só lançamentos gerais e operações marcadas em
                        <a href="{{ route('operations.index') }}">Operações</a> como visíveis no dashboard.
                    </small>
                </div>
                <div class="col-md-6">
                    <label class="form-label mb-1" for="dashboard-exclude-operations">Ocultar operações deste painel</label>
                    <select name="exclude_operation_ids[]" id="dashboard-exclude-operations" class="form-select" multiple
                            data-placeholder="Nenhuma — mostrar conforme o escopo acima">
                        @foreach($operations as $op)
                            <option value="{{ $op->id }}" @selected(in_array($op->id, $excludedIds, true))>
                                {{ $op->name }}
                                @unless($op->exclude_from_main_dashboard)
                                    (sempre no consolidado)
                                @endunless
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted">Use para tirar temporariamente um negócio do CFO sem alterar a operação.</small>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Aplicar</button>
                </div>
            </div>
        </form>
        @if(!$dashboardFilter->includeAllOperations || $excludedIds !== [])
            <p class="text-muted small mb-0 mt-2">
                @if(!$dashboardFilter->includeAllOperations)
                    Modo <strong>consolidado</strong>.
                @else
                    Modo <strong>todas as operações</strong>.
                @endif
                @if($excludedIds !== [])
                    Ocultas: {{ $operations->whereIn('id', $excludedIds)->pluck('name')->join(', ') }}.
                @endif
            </p>
        @endif
    </div>
</div>

@php
    $hygienePending = ($hygieneAudit['without_unit_in_ops_with_units'] ?? 0) > 0
        || count($hygieneAudit['wrong_company_types'] ?? []) > 0
        || (($hygieneAudit['geral_operation']['exclude_from_main_dashboard'] ?? false) && ($hygieneAudit['geral_operation']['transaction_count'] ?? 0) > 0);
@endphp
@if($hygienePending)
<div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <i class="bi bi-clipboard-check"></i>
        Há dados para revisar (empresas, operação Geral ou lançamentos sem apartamento).
    </div>
    <a href="{{ route('data-hygiene.index') }}" class="btn btn-info btn-sm">Abrir saneamento</a>
</div>
@endif
@if(isset($pendingCltPeriods) && $pendingCltPeriods->isNotEmpty())
<div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <i class="bi bi-briefcase"></i>
        <strong>{{ $pendingCltPeriods->count() }} salário(s) CLT</strong> aguardam confirmação do valor líquido deste mês.
    </div>
    <a href="{{ route('clt-salaries.index') }}" class="btn btn-warning btn-sm">Confirmar agora</a>
</div>
@endif
<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-success">
            <div class="inner">
                <h3 class="sensitive-value" data-sensitive-value="{{ $sensitiveMoney($cashFlow['current_month']->total_income) }}">{{ $moneyMask }}</h3>
                <p>Receitas (mês)</p>
            </div>
            <div class="icon"><i class="bi bi-arrow-down-circle"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-danger">
            <div class="inner">
                <h3 class="sensitive-value" data-sensitive-value="{{ $sensitiveMoney($cashFlow['current_month']->total_expense) }}">{{ $moneyMask }}</h3>
                <p>Despesas (mês)</p>
            </div>
            <div class="icon"><i class="bi bi-arrow-up-circle"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-primary">
            <div class="inner">
                <h3 class="sensitive-value" data-sensitive-value="{{ $sensitiveMoney($cashFlow['current_month']->net_cash_flow) }}">{{ $moneyMask }}</h3>
                <p>Fluxo líquido</p>
            </div>
            <div class="icon"><i class="bi bi-graph-up"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-warning">
            <div class="inner">
                <h3 class="sensitive-value" data-sensitive-value="{{ $sensitiveMoney($patrimony) }}">{{ $moneyMask }}</h3>
                <p>Patrimônio</p>
            </div>
            <div class="icon"><i class="bi bi-bank"></i></div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Fluxo de caixa (6 meses)</h3></div>
            <div class="card-body">
                <canvas id="chart-cashflow" class="sensitive-visual" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Despesas por categoria</h3></div>
            <div class="card-body">
                @if(count($chartExpenses['labels']) > 0)
                    <canvas id="chart-expenses" class="sensitive-visual" height="200"></canvas>
                @else
                    <p class="text-muted mb-0">Sem despesas categorizadas neste mês.</p>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="expense-category-modal" tabindex="-1" aria-labelledby="expense-category-modal-title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="expense-category-modal-title">Despesas da categoria</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">Categoria</dt>
                    <dd class="col-sm-7" id="expense-category-name">—</dd>

                    <dt class="col-sm-5">Total no mês</dt>
                    <dd class="col-sm-7 sensitive-value" id="expense-category-value">—</dd>

                    <dt class="col-sm-5">Participação</dt>
                    <dd class="col-sm-7" id="expense-category-percent">—</dd>

                    <dt class="col-sm-5">Transações</dt>
                    <dd class="col-sm-7" id="expense-category-count">—</dd>

                    <dt class="col-sm-5">Período do gráfico</dt>
                    <dd class="col-sm-7" id="expense-category-period">—</dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                <a href="#" class="btn btn-primary" id="expense-category-transactions-link">
                    Ver transações filtradas
                </a>
            </div>
        </div>
    </div>
</div>

@if(count($chartPatrimony['labels']) > 0)
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Patrimônio por item</h3></div>
            <div class="card-body">
                <canvas id="chart-patrimony" class="sensitive-visual" height="140"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6 d-flex align-items-center">
        <p class="mb-0 text-muted">
            Total: <strong class="sensitive-value" data-sensitive-value="{{ $sensitiveMoney($chartPatrimony['total']) }}">{{ $moneyMask }}</strong> —
            <a href="{{ route('assets.index') }}">Gerenciar patrimônio</a>
        </p>
    </div>
</div>
@else
<div class="alert alert-light border">
    Cadastre itens em <a href="{{ route('assets.index') }}">Patrimônio</a> para ver o gráfico de composição.
</div>
@endif

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Transações recentes</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Descrição</th>
                            <th>Operação</th>
                            <th>Tipo</th>
                            <th class="text-end">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentTransactions as $tx)
                            <tr>
                                <td>{{ $tx->transaction_date->format('d/m/Y') }}</td>
                                <td>{{ $tx->description }}</td>
                                <td>
                                    @if($tx->operation)
                                        <span class="badge text-bg-secondary">{{ $tx->operation->name }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge text-bg-{{ $tx->type->value === 'income' ? 'success' : 'danger' }}">
                                        {{ $tx->type->value }}
                                    </span>
                                </td>
                                <td class="text-end sensitive-value" data-sensitive-value="{{ $sensitiveMoney($tx->amount) }}">{{ $moneyMask }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">Nenhuma transação</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-outline card-{{ $forecast->risk_level === 'critical' ? 'danger' : 'info' }}">
            <div class="card-header"><h3 class="card-title">Previsão 90 dias</h3></div>
            <div class="card-body">
                <p><strong>Saldo projetado:</strong> <span class="sensitive-value" data-sensitive-value="{{ $sensitiveMoney($forecast->projected_balance) }}">{{ $moneyMask }}</span></p>
                <p><strong>Risco:</strong> <span class="badge text-bg-secondary">{{ $forecast->risk_level }}</span></p>
            </div>
        </div>
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h3 class="card-title mb-0">Alertas</h3>
                <a href="{{ route('alerts.index') }}" class="btn btn-sm btn-outline-primary">Ver</a>
            </div>
            <ul class="list-group list-group-flush">
                @forelse($openAlerts as $alert)
                    <li class="list-group-item">
                        <small class="text-muted">{{ $alert->severity->value }}</small><br>
                        {{ $alert->title }}
                    </li>
                @empty
                    <li class="list-group-item text-muted">Nenhum alerta aberto</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/pt-BR.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    const mask = 'R$ •••••';
    const button = document.getElementById('toggle-sensitive-values');

    window.dashboardMoneyMask = mask;
    window.dashboardSensitiveValuesVisible = false;

    function setSensitiveValuesVisible(visible) {
        window.dashboardSensitiveValuesVisible = visible;
        document.body.classList.toggle('dashboard-values-visible', visible);

        document.querySelectorAll('.sensitive-value[data-sensitive-value]').forEach((el) => {
            el.textContent = visible ? el.dataset.sensitiveValue : mask;
        });

        if (! button) {
            return;
        }

        const icon = button.querySelector('i');
        const label = button.querySelector('span');

        button.setAttribute('aria-pressed', visible ? 'true' : 'false');
        button.setAttribute('aria-label', visible ? 'Ocultar valores sensíveis' : 'Mostrar valores sensíveis');
        icon?.classList.toggle('bi-eye', ! visible);
        icon?.classList.toggle('bi-eye-slash', visible);
        if (label) {
            label.textContent = visible ? 'Ocultar valores' : 'Mostrar valores';
        }
    }

    button?.addEventListener('click', () => {
        setSensitiveValuesVisible(! window.dashboardSensitiveValuesVisible);
    });

    setSensitiveValuesVisible(false);
})();
</script>
<script>
(function () {
    const el = document.getElementById('dashboard-exclude-operations');
    if (el && typeof jQuery !== 'undefined') {
        jQuery(el).select2({
            theme: 'bootstrap-5',
            language: 'pt-BR',
            width: '100%',
            placeholder: el.dataset.placeholder || 'Selecione operações para ocultar…',
            allowClear: true,
            closeOnSelect: false,
        });
    }
})();
</script>
<script>
(function () {
    const cash = @json($chartCashFlow);
    const elCf = document.getElementById('chart-cashflow');
    if (elCf) {
        new Chart(elCf, {
            type: 'bar',
            data: {
                labels: cash.labels,
                datasets: [
                    { label: 'Receitas', data: cash.income, backgroundColor: 'rgba(25, 135, 84, 0.7)' },
                    { label: 'Despesas', data: cash.expense, backgroundColor: 'rgba(220, 53, 69, 0.7)' },
                    { label: 'Líquido', data: cash.net, type: 'line', borderColor: '#0d6efd', tension: 0.3, fill: false },
                ],
            },
            options: { responsive: true, scales: { y: { beginAtZero: true } } },
        });
    }

    const exp = @json($chartExpenses);
    const elEx = document.getElementById('chart-expenses');
    const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
    const transactionsUrl = "{{ route('transactions.index') }}";
    function expenseCategoryTransactionUrl(index) {
        const params = new URLSearchParams({ type: 'expense' });
        const categoryId = (exp.category_ids || [])[index] ?? null;

        if (categoryId) {
            params.set('category_id', categoryId);
        } else {
            params.set('missing', 'category');
        }

        return `${transactionsUrl}?${params.toString()}`;
    }

    function showExpenseCategoryModal(index) {
        const label = exp.labels[index] || 'Categoria';
        const value = Number(exp.values[index] || 0);
        const total = Number(exp.total || exp.values.reduce((sum, v) => sum + Number(v || 0), 0));
        const count = Number((exp.counts || [])[index] || 0);
        const pct = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';

        document.getElementById('expense-category-modal-title').textContent = label;
        document.getElementById('expense-category-name').textContent = label;
        const categoryValue = document.getElementById('expense-category-value');
        categoryValue.dataset.sensitiveValue = money.format(value);
        categoryValue.textContent = window.dashboardSensitiveValuesVisible ? money.format(value) : window.dashboardMoneyMask;
        document.getElementById('expense-category-percent').textContent = `${pct}% do total exibido`;
        document.getElementById('expense-category-count').textContent = count === 1 ? '1 transação' : `${count} transações`;
        document.getElementById('expense-category-period').textContent = `${formatDate(exp.month.from)} a ${formatDate(exp.month.to)}`;
        document.getElementById('expense-category-transactions-link').href = expenseCategoryTransactionUrl(index);

        const modal = document.getElementById('expense-category-modal');
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modal).show();
        }
    }

    function formatDate(value) {
        const [year, month, day] = String(value || '').split('-');

        return year && month && day ? `${day}/${month}/${year}` : 'Mês atual';
    }

    if (elEx && exp.labels.length) {
        new Chart(elEx, {
            type: 'doughnut',
            data: {
                labels: exp.labels,
                datasets: [{ data: exp.values, backgroundColor: ['#0d6efd','#6610f2','#d63384','#fd7e14','#ffc107','#198754','#20c997','#6c757d'] }],
            },
            options: {
                responsive: true,
                onClick(event, elements) {
                    if (elements.length) {
                        showExpenseCategoryModal(elements[0].index);
                    }
                },
                onHover(event, elements) {
                    event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
                },
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label(context) {
                                const values = context.dataset.data.map((v) => Number(v));
                                const total = values.reduce((sum, v) => sum + v, 0);
                                const value = Number(context.parsed ?? context.raw ?? 0);
                                const pct = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                                const fmt = value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                                const name = context.label || '';

                                return `${name}: ${fmt} (${pct}%)`;
                            },
                        },
                    },
                },
            },
        });
    }

    const pat = @json($chartPatrimony);
    const elPat = document.getElementById('chart-patrimony');
    if (elPat && pat.labels.length) {
        new Chart(elPat, {
            type: 'pie',
            data: {
                labels: pat.labels,
                datasets: [{ data: pat.values, backgroundColor: ['#ffc107','#0dcaf0','#198754','#6f42c1','#dc3545'] }],
            },
            options: { responsive: true },
        });
    }
})();
</script>
@endpush
