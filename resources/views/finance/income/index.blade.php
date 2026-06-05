@extends('layouts.adminlte')

@section('title', 'Receitas Unitárias')
@section('page_title', 'Receitas Unitárias')
@section('breadcrumb')
    <li class="breadcrumb-item active">Receitas Unitárias</li>
@endsection

@section('content')

@php
    $money = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
@endphp

{{-- KPIs --}}
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-lg-3">
        <div class="small-box text-bg-success">
            <div class="inner">
                <h4>{{ $money($totalMonth) }}</h4>
                <p>Recebido este mês</p>
            </div>
            <i class="small-box-icon bi bi-calendar-check" style="font-size:2.5rem;opacity:.3;position:absolute;right:1rem;top:50%;transform:translateY(-50%)"></i>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="small-box text-bg-primary">
            <div class="inner">
                <h4>{{ $money($totalYear) }}</h4>
                <p>Recebido no ano</p>
            </div>
            <i class="small-box-icon bi bi-graph-up-arrow" style="font-size:2.5rem;opacity:.3;position:absolute;right:1rem;top:50%;transform:translateY(-50%)"></i>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3 d-flex align-items-center">
        <a href="{{ route('recurring-income.index') }}" class="btn btn-outline-secondary w-100">
            <i class="bi bi-arrow-repeat me-1"></i> Ver receitas recorrentes
        </a>
    </div>
    <div class="col-sm-6 col-lg-3 d-flex align-items-center">
        <a href="{{ route('transactions.create') }}" class="btn btn-primary w-100">
            <i class="bi bi-plus-lg me-1"></i> Nova receita
        </a>
    </div>
</div>

{{-- Filtros --}}
<div class="card mb-3">
    <div class="card-header py-2 d-flex justify-content-between align-items-center"
         data-bs-toggle="collapse" data-bs-target="#income-filter-body"
         aria-expanded="{{ $filtersActive ? 'true' : 'false' }}"
         style="cursor:pointer; user-select:none">
        <h3 class="card-title mb-0 small">
            <i class="bi bi-funnel"></i> Filtros
            <i class="bi bi-chevron-down ms-1" style="font-size:.75rem; transition:transform .2s"
               id="income-filter-chevron"></i>
        </h3>
        @if($filtersActive)
            <span class="badge text-bg-primary">Filtros ativos</span>
        @endif
    </div>
    <div class="collapse @if($filtersActive) show @endif" id="income-filter-body">
        <div class="card-body py-2">
            <form method="GET" id="income-filter-form">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small mb-0">Descrição contém</label>
                        <input type="text" name="description" class="form-control form-control-sm"
                               value="{{ request('description') }}" placeholder="Ex.: aluguel, bônus…">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Data de</label>
                        <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Data até</label>
                        <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Categoria</label>
                        <select name="category_id" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Empresa</label>
                        <select name="company_id" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            @foreach($companies as $co)
                                <option value="{{ $co->id }}" @selected(request('company_id') == $co->id)>{{ $co->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Operação</label>
                        <select name="operation_id" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            @foreach($operations as $op)
                                <option value="{{ $op->id }}" @selected(request('operation_id') == $op->id)>{{ $op->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 col-lg-3 d-flex gap-1 align-items-end">
                        <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filtrar</button>
                        <a href="{{ route('income.index', ['clear' => 1]) }}" class="btn btn-sm btn-outline-secondary">Limpar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Tabela --}}
<div class="card">
    <div class="card-body table-responsive p-0">
        <table id="dt-income" class="table table-hover table-sm mb-0">
            <thead>
                <tr>
                    <th style="width:90px">Data</th>
                    <th>Descrição</th>
                    <th>Empresa</th>
                    <th>Categoria</th>
                    <th>Operação</th>
                    <th class="text-end">Valor</th>
                    <th style="width:50px"></th>
                </tr>
            </thead>
            <tbody>
                @php $lastMonth = null; @endphp
                @forelse($transactions as $tx)
                    @php $month = $tx->transaction_date->translatedFormat('F Y'); @endphp
                    @if($month !== $lastMonth)
                        @php $lastMonth = $month; @endphp
                        <tr class="table-light">
                            <td colspan="7" class="fw-semibold small text-uppercase text-muted py-1 px-3">
                                {{ $month }}
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td class="text-muted small">{{ $tx->transaction_date->format('d/m') }}</td>
                        <td>{{ $tx->description }}</td>
                        <td class="small">{{ $tx->company?->name ?? '—' }}</td>
                        <td>
                            @if($tx->category)
                                <span class="badge text-bg-secondary">{{ $tx->category->name }}</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td class="small">{{ $tx->operation?->name ?? '—' }}</td>
                        <td class="text-end text-success fw-semibold">{{ $money($tx->amount) }}</td>
                        <td class="text-end">
                            <a href="{{ route('transactions.edit', $tx) }}"
                               class="btn btn-xs btn-outline-secondary btn-sm" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            Nenhuma receita unitária encontrada.
                            @if(!$filtersActive)
                                <a href="{{ route('transactions.create') }}">Cadastrar agora</a>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        {{ $transactions->links() }}
    </div>
</div>

@endsection

@push('scripts')
<script>
new DataTable('#dt-income', {
    searching: true,
    paging: false,
    info: false,
    language: { url: 'https://cdn.datatables.net/plug-ins/2.0.8/i18n/pt-BR.json' },
    columnDefs: [{ targets: -1, orderable: false, searchable: false }],
    layout: {
        topStart: 'search',
        topEnd: null,
        bottomStart: null,
        bottomEnd: null,
    },
});
</script>
<script>
(function () {
    const collapse = document.getElementById('income-filter-body');
    const chevron  = document.getElementById('income-filter-chevron');
    if (!collapse || !chevron) return;
    collapse.addEventListener('show.bs.collapse', () => { chevron.style.transform = 'rotate(180deg)'; });
    collapse.addEventListener('hide.bs.collapse', () => { chevron.style.transform = ''; });
    if (collapse.classList.contains('show')) chevron.style.transform = 'rotate(180deg)';
})();
</script>
@endpush
