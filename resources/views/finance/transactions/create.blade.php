@extends('layouts.adminlte')

@section('title', 'Nova transação')
@section('page_title', 'Nova transação')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('transactions.index') }}">Transações</a></li>
    <li class="breadcrumb-item active">Nova</li>
@endsection

@section('content')
<div class="card card-primary">
    <form action="{{ route('transactions.store') }}" method="POST">
        @csrf
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Tipo</label>
                    <select name="type" id="tx-type" class="form-select" required>
                        <option value="expense" selected>Despesa</option>
                        <option value="income">Receita</option>
                        <option value="transfer">Transferência</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Valor (R$)</label>
                    <input type="number" step="0.01" name="amount" class="form-control" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Data</label>
                    <input type="date" name="transaction_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="confirmed" selected>Confirmado</option>
                        <option value="pending">Pendente</option>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Descrição</label>
                    <input type="text" name="description" class="form-control" placeholder="Assinatura OpenAI, salário cliente X..." required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Contraparte</label>
                    <input type="text" name="counterparty" class="form-control" placeholder="Nome no extrato (auto-categoriza se vazio a categoria)">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Categoria</label>
                    <select name="category_id" class="form-select">
                        <option value="">— Detectar automaticamente —</option>
                        @foreach($categories->groupBy('type') as $type => $group)
                            <optgroup label="{{ $type === 'income' ? 'Receitas' : 'Despesas' }}">
                                @foreach($group as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Empresa vinculada</label>
                    <select name="company_id" class="form-select">
                        <option value="">— Nenhuma —</option>
                        @foreach($companies as $c)
                            <option value="{{ $c->id }}" data-type="{{ $c->type->value }}">
                                [{{ $c->type->label() }}] {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted">Receitas: empresas que te pagam. Despesas: sua empresa ou fornecedor.</small>
                </div>

                <div class="col-12">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="is_recurring" value="1" id="is-recurring">
                        <label class="form-check-label" for="is-recurring">É recorrente (assinatura, salário, aluguel...)</label>
                    </div>
                </div>
                <div class="col-md-4 mb-3" id="frequency-field" style="display:none">
                    <label class="form-label">Recorrência</label>
                    <select name="recurrence_frequency" class="form-select">
                        @foreach($frequencies as $freq)
                            <option value="{{ $freq->value }}" @selected($freq->value === 'monthly')>{{ $freq->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Salvar</button>
            <a href="{{ route('transactions.index') }}" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('is-recurring').addEventListener('change', function () {
    document.getElementById('frequency-field').style.display = this.checked ? '' : 'none';
});
</script>
@endpush
