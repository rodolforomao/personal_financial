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
    $isDetail = ($view ?? 'resumo') === 'detalhado';
@endphp

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <p class="text-muted mb-0">
        @if($isDetail)
            Listagem detalhada dos lançamentos do relatório.
        @else
            Resumo financeiro com os mesmos critérios de filtro das transações.
        @endif
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
        @if(!$isDetail)
            <a href="{{ $detailUrl }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-table"></i> Ver detalhado
            </a>
        @endif
    </div>
</div>

@include('reports.partials._filters')
@include('reports.partials._view_switch')

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

@if($isDetail)
    @include('reports.partials._detail_table')
@else
    @include('reports.partials._summary', ['money' => $money])
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
@if($isDetail && count($detailRows) > 0)
<script>
(function () {
    const tableEl = document.getElementById('report-transactions-table');
    if (!tableEl || typeof DataTable === 'undefined') return;

    new DataTable(tableEl, {
        searching: true,
        order: [[2, 'asc'], [0, 'asc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, 250, -1], ['10', '25', '50', '100', '250', 'Todos']],
        language: {
            url: 'https://cdn.datatables.net/plug-ins/2.0.8/i18n/pt-BR.json',
        },
        columnDefs: [
            { targets: 7, className: 'text-end' },
            { targets: -1, orderable: false, searchable: false },
        ],
    });
})();
</script>
@endif
@endpush
