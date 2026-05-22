@extends('layouts.adminlte')

@section('title', 'Transações')
@section('page_title', 'Transações')
@section('breadcrumb')
    <li class="breadcrumb-item active">Transações</li>
@endsection

@section('content')
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-0">Categoria</label>
                <select name="category_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-0">Tipo</label>
                <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <option value="expense" @selected(request('type') === 'expense')>Despesa</option>
                    <option value="income" @selected(request('type') === 'income')>Receita</option>
                </select>
            </div>
            <div class="col-md-2">
                <a href="{{ route('transactions.index') }}" class="btn btn-sm btn-outline-secondary">Limpar</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Lançamentos</h3>
        <div class="card-tools">
            <a href="{{ route('categories.index') }}" class="btn btn-outline-secondary btn-sm me-1">Categorias</a>
            <a href="{{ route('transactions.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Nova transação
            </a>
        </div>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Descrição</th>
                    <th>Categoria</th>
                    <th>Empresa</th>
                    <th>Tipo</th>
                    <th>Recorrência</th>
                    <th class="text-end">Valor</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transactions as $tx)
                    <tr>
                        <td>{{ $tx->transaction_date->format('d/m/Y') }}</td>
                        <td>
                            {{ $tx->description }}
                            @if($tx->counterparty)<br><small class="text-muted">{{ $tx->counterparty }}</small>@endif
                        </td>
                        <td>
                            @if($tx->category)
                                <span class="badge text-bg-secondary">{{ $tx->category->name }}</span>
                            @else
                                <span class="text-muted">Sem categoria</span>
                            @endif
                        </td>
                        <td>{{ $tx->company?->name ?? '—' }}</td>
                        <td><span class="badge text-bg-{{ $tx->type->value === 'income' ? 'success' : 'danger' }}">{{ $tx->type->value }}</span></td>
                        <td>
                            @if($tx->is_recurring && $tx->recurrence_frequency)
                                <i class="bi bi-arrow-repeat"></i> {{ $tx->recurrence_frequency->label() }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-end fw-semibold">R$ {{ number_format($tx->amount, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($transactions->hasPages())
        <div class="card-footer">{{ $transactions->links() }}</div>
    @endif
</div>
@endsection
