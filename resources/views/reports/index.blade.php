@extends('layouts.adminlte')

@section('title', 'Relatórios')
@section('page_title', 'Relatórios')
@section('breadcrumb')
    <li class="breadcrumb-item active">Relatórios</li>
@endsection

@section('content')
@php
    $totals = $report['totals'];
    $money = fn (float $v) => 'R$ '.number_format($v, 2, ',', '.');
@endphp

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <p class="text-muted mb-0">
        Resumo financeiro com os mesmos critérios de filtro das transações.
        <strong>{{ $report['period_label'] }}</strong>
    </p>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('reports.export', array_merge($exportQuery ?? [], ['format' => 'xlsx'])) }}"
           class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-spreadsheet"></i> Baixar XLSX
        </a>
        <a href="{{ route('reports.export', array_merge($exportQuery ?? [], ['format' => 'pdf'])) }}"
           class="btn btn-danger btn-sm">
            <i class="bi bi-file-earmark-pdf"></i> Baixar PDF
        </a>
        <a href="{{ $report['transaction_list_url'] }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-list-ul"></i> Ver lançamentos
        </a>
    </div>
</div>

@include('reports.partials._filters')

<div class="row g-3 mb-3">
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-success">
            <div class="inner">
                <h3>{{ $money($totals['income']) }}</h3>
                <p>Receitas ({{ $totals['income_count'] }} lanç.)</p>
            </div>
            <i class="small-box-icon bi bi-arrow-down-circle"></i>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-danger">
            <div class="inner">
                <h3>{{ $money($totals['expense']) }}</h3>
                <p>Despesas ({{ $totals['expense_count'] }} lanç.)</p>
            </div>
            <i class="small-box-icon bi bi-arrow-up-circle"></i>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-primary">
            <div class="inner">
                <h3>{{ $money($totals['net']) }}</h3>
                <p>Resultado líquido</p>
            </div>
            <i class="small-box-icon bi bi-calculator"></i>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-secondary">
            <div class="inner">
                <h3>{{ $totals['transaction_count'] }}</h3>
                <p>Lançamentos no relatório</p>
            </div>
            <i class="small-box-icon bi bi-hash"></i>
        </div>
    </div>
</div>

@if($totals['transaction_count'] === 0)
    <div class="alert alert-info">
        Nenhum lançamento confirmado ou conciliado corresponde aos filtros.
        <a href="{{ route('reports.index') }}">Limpar filtros</a> ou
        <a href="{{ route('transactions.index') }}">ir às transações</a>.
    </div>
@else
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card card-outline card-secondary h-100">
            <div class="card-header">
                <h3 class="card-title mb-0">Por operação</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Operação</th>
                                <th class="text-end">Receita</th>
                                <th class="text-end">Despesa</th>
                                <th class="text-end">Líquido</th>
                                <th class="text-end">Qtd.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report['by_operation'] as $row)
                                <tr>
                                    <td>
                                        @if($row['operation_id'])
                                            <a href="{{ route('reports.index', array_merge(request()->query(), ['operation_id' => $row['operation_id'], 'operation_unit_id' => null])) }}">
                                                {{ $row['label'] }}
                                            </a>
                                        @else
                                            {{ $row['label'] }}
                                        @endif
                                    </td>
                                    <td class="text-end text-success">{{ $money($row['income']) }}</td>
                                    <td class="text-end text-danger">{{ $money($row['expense']) }}</td>
                                    <td class="text-end"><strong>{{ $money($row['net']) }}</strong></td>
                                    <td class="text-end text-muted">{{ $row['count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-outline card-secondary h-100">
            <div class="card-header">
                <h3 class="card-title mb-0">Por categoria</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Categoria</th>
                                <th class="text-end">Receita</th>
                                <th class="text-end">Despesa</th>
                                <th class="text-end">Líquido</th>
                                <th class="text-end">Qtd.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report['by_category'] as $row)
                                <tr>
                                    <td>
                                        @if($row['category_id'])
                                            <a href="{{ route('reports.index', array_merge(request()->query(), ['category_id' => $row['category_id']])) }}">
                                                {{ $row['label'] }}
                                            </a>
                                        @else
                                            {{ $row['label'] }}
                                        @endif
                                    </td>
                                    <td class="text-end text-success">{{ $money($row['income']) }}</td>
                                    <td class="text-end text-danger">{{ $money($row['expense']) }}</td>
                                    <td class="text-end"><strong>{{ $money($row['net']) }}</strong></td>
                                    <td class="text-end text-muted">{{ $row['count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card card-outline card-secondary">
            <div class="card-header">
                <h3 class="card-title mb-0">Por empresa</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Empresa</th>
                                <th class="text-end">Receita</th>
                                <th class="text-end">Despesa</th>
                                <th class="text-end">Líquido</th>
                                <th class="text-end">Qtd.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report['by_company'] as $row)
                                <tr>
                                    <td>
                                        @if($row['company_id'])
                                            <a href="{{ route('reports.index', array_merge(request()->query(), ['company_id' => $row['company_id']])) }}">
                                                {{ $row['label'] }}
                                            </a>
                                        @else
                                            {{ $row['label'] }}
                                        @endif
                                    </td>
                                    <td class="text-end text-success">{{ $money($row['income']) }}</td>
                                    <td class="text-end text-danger">{{ $money($row['expense']) }}</td>
                                    <td class="text-end"><strong>{{ $money($row['net']) }}</strong></td>
                                    <td class="text-end text-muted">{{ $row['count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const opSelect = document.getElementById('report-filter-operation-id');
    const unitSelect = document.getElementById('report-filter-operation-unit-id');
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
