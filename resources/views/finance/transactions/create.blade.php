@extends('layouts.adminlte')

@section('title', 'Nova transação')
@section('page_title', 'Nova transação')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('transactions.index') }}">Transações</a></li>
    <li class="breadcrumb-item active">Nova</li>
@endsection

@section('content')
@include('finance.transactions._receipt_scanner', ['scanTarget' => 'transaction-form', 'scanTransactionId' => null])

<div class="card card-primary">
    <form action="{{ route('transactions.store') }}" method="POST" id="transaction-form">
        @csrf
        <input type="hidden" name="document_id" id="tx-link-document-id" value="">
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
                    <input type="text" name="description" id="tx-description" class="form-control" placeholder="Assinatura OpenAI, salário cliente X..." required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Contraparte</label>
                    <input type="text" name="counterparty" id="tx-counterparty" class="form-control" placeholder="Nome no extrato / favorecido">
                </div>

                @include('finance.transactions._payment_fields')

                @include('finance.transactions._category_suggestions', [
                    'categories' => $categories,
                    'selectedCategoryId' => old('category_id'),
                ])
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

                @include('finance.transactions._operation_fields', [
                    'operations' => $operations,
                    'preselectedOperationId' => $preselectedOperationId ?? null,
                    'preselectedUnitId' => $preselectedUnitId ?? null,
                ])

                <div class="col-12" id="mirror-personal-wrapper" style="display:none">
                    <div class="alert alert-warning py-2 mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-arrow-left-right fs-5"></i>
                        <div>
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" name="mirror_personal_capital" value="1" id="mirror-personal-capital">
                                <label class="form-check-label fw-semibold" for="mirror-personal-capital">
                                    Registrar saída do capital pessoal (double-entry)
                                </label>
                            </div>
                            <small class="text-muted">Cria automaticamente uma despesa espelhada no dashboard principal — representa que o dinheiro saiu do seu bolso.</small>
                        </div>
                    </div>
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

(function () {
    const typeSelect = document.getElementById('tx-type');
    const opSelect = document.getElementById('tx-operation-id');
    const wrapper = document.getElementById('mirror-personal-wrapper');
    const checkbox = document.getElementById('mirror-personal-capital');

    function syncMirrorVisibility() {
        const show = typeSelect?.value === 'income' && opSelect?.value;
        wrapper.style.display = show ? '' : 'none';
        if (!show) checkbox.checked = false;
    }

    typeSelect?.addEventListener('change', syncMirrorVisibility);
    opSelect?.addEventListener('change', syncMirrorVisibility);
    syncMirrorVisibility();
})();

(function () {
    const docId = sessionStorage.getItem('link_document_id');
    if (docId) {
        const hidden = document.getElementById('tx-link-document-id');
        if (hidden) hidden.value = docId;
        sessionStorage.removeItem('link_document_id');
    }
    const raw = sessionStorage.getItem('transaction_prefill');
    if (!raw || typeof window.applyReceiptExtract !== 'function') return;
    try {
        const data = JSON.parse(raw);
        window.applyReceiptExtract(data);
        if (data.category_suggestion && typeof window.showCategorySuggestion === 'function') {
            window.showCategorySuggestion(data.category_suggestion);
        }
        sessionStorage.removeItem('transaction_prefill');
    } catch (e) {}
})();
</script>
@endpush
