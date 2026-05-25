@extends('layouts.adminlte')

@section('title', 'Categorias')
@section('page_title', 'Classificação de gastos')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('transactions.index') }}">Transações</a></li>
    <li class="breadcrumb-item active">Categorias</li>
@endsection

@section('content')
<div class="row">
    <div class="col-md-4">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title">Nova categoria</h3></div>
            <form action="{{ route('categories.store') }}" method="POST">
                @csrf
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" name="name" class="form-control" placeholder="Marketing, Impostos..." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipo</label>
                        <select name="type" class="form-select">
                            <option value="expense" selected>Despesa</option>
                            <option value="income">Receita</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cor (opcional)</label>
                        <input type="color" name="color" class="form-control form-control-color" value="#6c757d">
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary w-100">Adicionar</button>
                </div>
            </form>
        </div>
        <div class="card card-warning card-outline">
            <div class="card-body">
                <strong>Sem categoria este mês</strong>
                <p class="mb-0 fs-4">R$ {{ number_format($uncategorized, 2, ',', '.') }}</p>
                <a href="{{ route('transactions.index', ['type' => 'expense']) }}" class="btn btn-sm btn-outline-warning mt-2">Ver transações</a>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Gastos por categoria (mês atual)</h3></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Categoria</th><th class="text-end">Total</th><th></th></tr></thead>
                    <tbody>
                        @forelse($categories as $cat)
                            <tr>
                                <td>
                                    <span class="badge rounded-pill" style="background:{{ $cat->color ?? '#6c757d' }}">{{ $cat->name }}</span>
                                </td>
                                <td class="text-end fw-semibold">R$ {{ number_format($cat->month_total, 2, ',', '.') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('transactions.index', ['category_id' => $cat->id]) }}" class="btn btn-xs btn-outline-primary btn-sm">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted text-center">Nenhuma categoria de despesa</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-muted small mt-2">
            <a href="{{ route('categorization-rules.index') }}">Regras de auto categorização</a>
            (Uber → Transporte, Evento B3 → Dividendos, etc.) e IA quando habilitada.
            Deixe a categoria em branco ao lançar para aplicar automaticamente.
        </p>
    </div>
</div>
@endsection
