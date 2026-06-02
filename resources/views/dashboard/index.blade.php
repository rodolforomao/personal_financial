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

    .dashboard-summary .small-box {
        overflow: hidden;
        position: relative;
    }

    .dashboard-summary .inner {
        padding-right: 4.75rem;
    }

    .dashboard-summary .small-box-icon {
        align-items: center;
        display: flex;
        font-size: 3rem;
        height: 3.5rem;
        justify-content: center;
        line-height: 1;
        opacity: .28;
        pointer-events: none;
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        width: 3.5rem;
    }
</style>
@endpush

@section('content')
@php
    $excludedIds     = $dashboardFilter->normalizedExcludeIds();
    $moneyMask       = 'R$ •••••';
    $sensitiveMoney  = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');

    $riskLabel = ['low' => 'Baixo', 'medium' => 'Médio', 'high' => 'Alto', 'critical' => 'Crítico'];
    $riskBadge = ['low' => 'success', 'medium' => 'warning', 'high' => 'warning', 'critical' => 'danger'];

    $severityLabel = ['info' => 'Informação', 'warning' => 'Atenção', 'critical' => 'Crítico'];
    $severityBadge = ['info' => 'info', 'warning' => 'warning', 'critical' => 'danger'];

    $netPositive      = (float) $cashFlow['current_month']->net_cash_flow >= 0;
    $expenseChartMonth = \Illuminate\Support\Carbon::parse($chartExpenses['month']['from'])->translatedFormat('F/y');
@endphp

{{-- Botão revelar valores --}}
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

{{-- Filtro de escopo (colapsável) --}}
<div class="card card-outline card-secondary mb-3" id="filter-card">
    <div class="card-header py-2 d-flex align-items-center gap-2"
         role="button"
         data-bs-toggle="collapse"
         data-bs-target="#filter-body"
         aria-expanded="false"
         aria-controls="filter-body"
         style="cursor:pointer; user-select:none">
        <i class="bi bi-funnel flex-shrink-0"></i>
        <span class="fw-semibold me-2">Escopo</span>
        {{-- Resumo visível quando colapsado --}}
        <span id="filter-summary" class="d-flex align-items-center gap-1 flex-wrap">
            @if($dashboardFilter->includeAllOperations)
                <span class="badge text-bg-secondary">Todas as operações</span>
            @else
                <span class="badge text-bg-secondary">Consolidado</span>
            @endif
            @foreach($operations->whereIn('id', $excludedIds) as $op)
                <span class="badge text-bg-warning">− {{ $op->name }}</span>
            @endforeach
        </span>
        <i class="bi bi-chevron-down ms-auto filter-chevron" style="transition: transform .2s"></i>
    </div>
    <div class="collapse" id="filter-body">
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
                            Desligado: exibe apenas lançamentos gerais e operações marcadas como visíveis no dashboard
                            (ver <a href="{{ route('operations.index') }}">Operações</a>).
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
        </div>
    </div>
</div>

{{-- Alertas de saneamento / CLT --}}
@php
    $hygienePending = ($hygieneAudit['without_unit_in_ops_with_units'] ?? 0) > 0
        || count($hygieneAudit['wrong_company_types'] ?? []) > 0
        || (($hygieneAudit['geral_operation']['exclude_from_main_dashboard'] ?? false) && ($hygieneAudit['geral_operation']['transaction_count'] ?? 0) > 0);
@endphp
@if($hygienePending)
<div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <i class="bi bi-clipboard-check"></i>
        Há dados para revisar (empresas, operação Geral ou lançamentos sem unidade).
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

{{-- KPIs do mês --}}
<div class="row g-3 mb-3 dashboard-summary">
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-success">
            <div class="inner">
                <h3 class="sensitive-value" data-sensitive-value="{{ $sensitiveMoney($cashFlow['current_month']->total_income) }}">{{ $moneyMask }}</h3>
                <p class="mb-0">Receitas (mês)</p>
                @php $pct = $cashFlow['income_change_pct']; @endphp
                @if($pct != 0)
                    <small class="opacity-75">
                        {{ $pct > 0 ? '▲' : '▼' }} {{ number_format(abs($pct), 1) }}% vs mês anterior
                    </small>
                @endif
            </div>
            <i class="small-box-icon bi bi-arrow-down-circle"></i>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-danger">
            <div class="inner">
                <h3 class="sensitive-value" data-sensitive-value="{{ $sensitiveMoney($cashFlow['current_month']->total_expense) }}">{{ $moneyMask }}</h3>
                <p class="mb-0">Despesas (mês)</p>
                @php $pct = $cashFlow['expense_change_pct']; @endphp
                @if($pct != 0)
                    <small class="opacity-75">
                        {{ $pct > 0 ? '▲' : '▼' }} {{ number_format(abs($pct), 1) }}% vs mês anterior
                    </small>
                @endif
            </div>
            <i class="small-box-icon bi bi-arrow-up-circle"></i>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-{{ $netPositive ? 'primary' : 'danger' }}">
            <div class="inner">
                <h3 class="sensitive-value" data-sensitive-value="{{ $sensitiveMoney($cashFlow['current_month']->net_cash_flow) }}">{{ $moneyMask }}</h3>
                <p>Fluxo líquido (mês)</p>
            </div>
            <i class="small-box-icon bi bi-graph-up"></i>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-warning">
            <div class="inner">
                <h3 class="sensitive-value" data-sensitive-value="{{ $sensitiveMoney($patrimony) }}">{{ $moneyMask }}</h3>
                <p>Patrimônio total</p>
            </div>
            <i class="small-box-icon bi bi-bank"></i>
        </div>
    </div>
</div>

{{-- Gráficos: Fluxo de caixa + Despesas por categoria --}}
<div class="row mb-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Fluxo de caixa — últimos 6 meses</h3>
            </div>
            <div class="card-body">
                <canvas id="chart-cashflow" class="sensitive-visual" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Despesas por categoria</h3>
                <span class="card-tools text-muted small">{{ $expenseChartMonth }}</span>
            </div>
            <div class="card-body">
                @if(count($chartExpenses['labels']) > 0)
                    <canvas id="chart-expenses" class="sensitive-visual" height="200"></canvas>
                @else
                    <p class="text-muted mb-0">Sem despesas categorizadas em {{ $expenseChartMonth }}.</p>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Modal detalhe de categoria de despesa --}}
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

                    <dt class="col-sm-5">Período</dt>
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

{{-- Gráfico de patrimônio --}}
@if(count($chartPatrimony['labels']) > 0)
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Composição do patrimônio</h3>
                <span class="text-muted small">
                    Total: <strong class="sensitive-value" data-sensitive-value="{{ $sensitiveMoney($chartPatrimony['total']) }}">{{ $moneyMask }}</strong>
                    &nbsp;·&nbsp;
                    <a href="{{ route('assets.index') }}">Gerenciar</a>
                </span>
            </div>
            <div class="card-body">
                <canvas id="chart-patrimony" class="sensitive-visual" height="80"></canvas>
            </div>
        </div>
    </div>
</div>
@else
<div class="alert alert-light border mb-3">
    Cadastre itens em <a href="{{ route('assets.index') }}">Patrimônio</a> para ver o gráfico de composição.
</div>
@endif

{{-- Transações recentes + Previsão + Alertas --}}
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Transações recentes</h3>
                <a href="{{ route('transactions.index') }}" class="btn btn-sm btn-outline-secondary">Ver todas</a>
            </div>
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
                                <td>
                                    <a href="{{ route('transactions.edit', $tx) }}" class="text-reset text-decoration-none">
                                        {{ $tx->description }}
                                    </a>
                                </td>
                                <td>
                                    @if($tx->operation)
                                        <span class="badge text-bg-secondary">{{ $tx->operation->name }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge text-bg-{{ $tx->type === \App\Core\Enums\TransactionType::Income ? 'success' : 'danger' }}">
                                        {{ $tx->type->label() }}
                                    </span>
                                </td>
                                <td class="text-end sensitive-value" data-sensitive-value="{{ $sensitiveMoney($tx->amount) }}">{{ $moneyMask }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">Nenhuma transação</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        {{-- Previsão 90 dias --}}
        @php
            $rLevel = $forecast->risk_level;
            $rText  = $riskLabel[$rLevel]  ?? ucfirst($rLevel);
            $rColor = $riskBadge[$rLevel]  ?? 'secondary';
        @endphp
        <div class="card card-outline card-{{ $rColor }} mb-3">
            <div class="card-header">
                <h3 class="card-title">Previsão — próximos 90 dias</h3>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-6 fw-normal text-muted">Receita projetada</dt>
                    <dd class="col-6 text-end sensitive-value" data-sensitive-value="{{ $sensitiveMoney($forecast->projected_income) }}">{{ $moneyMask }}</dd>

                    <dt class="col-6 fw-normal text-muted">Despesa projetada</dt>
                    <dd class="col-6 text-end sensitive-value" data-sensitive-value="{{ $sensitiveMoney($forecast->projected_expense) }}">{{ $moneyMask }}</dd>

                    <dt class="col-6 fw-semibold">Saldo projetado</dt>
                    <dd class="col-6 text-end fw-semibold sensitive-value" data-sensitive-value="{{ $sensitiveMoney($forecast->projected_balance) }}">{{ $moneyMask }}</dd>

                    <dt class="col-6 fw-normal text-muted mt-1">Risco</dt>
                    <dd class="col-6 text-end mt-1">
                        <span class="badge text-bg-{{ $rColor }}">{{ $rText }}</span>
                    </dd>
                </dl>
            </div>
        </div>

        {{-- Alertas --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Alertas</h3>
                <a href="{{ route('alerts.index') }}" class="btn btn-sm btn-outline-primary">Ver todos</a>
            </div>
            <ul class="list-group list-group-flush">
                @forelse($openAlerts as $alert)
                    @php
                        $sev      = $alert->severity->value;
                        $sevText  = $severityLabel[$sev]  ?? ucfirst($sev);
                        $sevColor = $severityBadge[$sev]  ?? 'secondary';
                    @endphp
                    <a href="{{ route('alerts.index') }}#alert-{{ $alert->id }}"
                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-start gap-2 py-2 text-decoration-none">
                        <span class="text-body">{{ $alert->title }}</span>
                        <span class="badge text-bg-{{ $sevColor }} flex-shrink-0">{{ $sevText }}</span>
                    </a>
                @empty
                    <li class="list-group-item text-muted py-3 text-center">Nenhum alerta aberto</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const STORAGE_KEY = 'dashboard-filter-open';
    const collapseEl = document.getElementById('filter-body');
    const chevron    = document.querySelector('.filter-chevron');

    if (!collapseEl) return;

    function setChevron(open) {
        if (chevron) chevron.style.transform = open ? 'rotate(180deg)' : '';
    }

    // Restaura estado salvo (padrão: fechado)
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved === 'open') {
        collapseEl.classList.add('show');
        setChevron(true);
    }

    collapseEl.addEventListener('show.bs.collapse',  () => { localStorage.setItem(STORAGE_KEY, 'open');   setChevron(true);  });
    collapseEl.addEventListener('hide.bs.collapse',  () => { localStorage.setItem(STORAGE_KEY, 'closed'); setChevron(false); });
})();
</script>
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
    const brl = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 }).format(v);
    const brlFull = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v);

    // Gráfico: Fluxo de caixa (6 meses)
    const cash = @json($chartCashFlow);
    const elCf = document.getElementById('chart-cashflow');
    if (elCf) {
        new Chart(elCf, {
            type: 'bar',
            data: {
                labels: cash.labels,
                datasets: [
                    { label: 'Receitas',  data: cash.income,   backgroundColor: 'rgba(25, 135, 84, 0.7)' },
                    { label: 'Despesas',  data: cash.expense,  backgroundColor: 'rgba(220, 53, 69, 0.7)' },
                    { label: 'Líquido',   data: cash.net,      type: 'line', borderColor: '#0d6efd', tension: 0.3, fill: false },
                ],
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: (v) => brl(v) },
                    },
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: (ctx) => `${ctx.dataset.label}: ${brlFull(ctx.parsed.y ?? ctx.raw)}`,
                        },
                    },
                },
            },
        });
    }

    // Gráfico: Despesas por categoria
    const exp = @json($chartExpenses);
    const elEx = document.getElementById('chart-expenses');
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
        categoryValue.dataset.sensitiveValue = brlFull(value);
        categoryValue.textContent = window.dashboardSensitiveValuesVisible ? brlFull(value) : window.dashboardMoneyMask;

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
        return year && month && day ? `${day}/${month}/${year}` : '—';
    }

    if (elEx && exp.labels.length) {
        new Chart(elEx, {
            type: 'doughnut',
            data: {
                labels: exp.labels,
                datasets: [{
                    data: exp.values,
                    backgroundColor: ['#0d6efd','#6610f2','#d63384','#fd7e14','#ffc107','#198754','#20c997','#6c757d'],
                }],
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
                                return `${context.label}: ${brlFull(value)} (${pct}%)`;
                            },
                        },
                    },
                },
            },
        });
    }

    // Gráfico: Patrimônio
    const pat = @json($chartPatrimony);
    const elPat = document.getElementById('chart-patrimony');
    if (elPat && pat.labels.length) {
        new Chart(elPat, {
            type: 'pie',
            data: {
                labels: pat.labels,
                datasets: [{
                    data: pat.values,
                    backgroundColor: ['#ffc107','#0dcaf0','#198754','#6f42c1','#dc3545','#0d6efd','#fd7e14','#20c997'],
                }],
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => {
                                const total = ctx.dataset.data.reduce((s, v) => s + Number(v), 0);
                                const value = Number(ctx.parsed ?? ctx.raw ?? 0);
                                const pct = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                                return `${ctx.label}: ${brlFull(value)} (${pct}%)`;
                            },
                        },
                    },
                },
            },
        });
    }
})();
</script>
@endpush
