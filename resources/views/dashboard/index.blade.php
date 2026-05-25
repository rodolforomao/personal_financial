@extends('layouts.adminlte')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard CFO')
@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
@endpush

@section('content')
@php
    $excludedIds = $dashboardFilter->normalizedExcludeIds();
@endphp

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
                <h3>R$ {{ number_format($cashFlow['current_month']->total_income, 2, ',', '.') }}</h3>
                <p>Receitas (mês)</p>
            </div>
            <div class="icon"><i class="bi bi-arrow-down-circle"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-danger">
            <div class="inner">
                <h3>R$ {{ number_format($cashFlow['current_month']->total_expense, 2, ',', '.') }}</h3>
                <p>Despesas (mês)</p>
            </div>
            <div class="icon"><i class="bi bi-arrow-up-circle"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-primary">
            <div class="inner">
                <h3>R$ {{ number_format($cashFlow['current_month']->net_cash_flow, 2, ',', '.') }}</h3>
                <p>Fluxo líquido</p>
            </div>
            <div class="icon"><i class="bi bi-graph-up"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-warning">
            <div class="inner">
                <h3>R$ {{ number_format($patrimony, 2, ',', '.') }}</h3>
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
                <canvas id="chart-cashflow" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Despesas por categoria</h3></div>
            <div class="card-body">
                @if(count($chartExpenses['labels']) > 0)
                    <canvas id="chart-expenses" height="200"></canvas>
                @else
                    <p class="text-muted mb-0">Sem despesas categorizadas neste mês.</p>
                @endif
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
                <canvas id="chart-patrimony" height="140"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6 d-flex align-items-center">
        <p class="mb-0 text-muted">
            Total: <strong>R$ {{ number_format($chartPatrimony['total'], 2, ',', '.') }}</strong> —
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
                                <td class="text-end">R$ {{ number_format($tx->amount, 2, ',', '.') }}</td>
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
                <p><strong>Saldo projetado:</strong> R$ {{ number_format($forecast->projected_balance, 2, ',', '.') }}</p>
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
    if (elEx && exp.labels.length) {
        new Chart(elEx, {
            type: 'doughnut',
            data: {
                labels: exp.labels,
                datasets: [{ data: exp.values, backgroundColor: ['#0d6efd','#6610f2','#d63384','#fd7e14','#ffc107','#198754','#20c997','#6c757d'] }],
            },
            options: {
                responsive: true,
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
