@extends('layouts.adminlte')

@section('title', 'Editar transação #'.$transaction->id)
@section('page_title', 'Editar transação #'.$transaction->id)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('transactions.index') }}">Transações</a></li>
    <li class="breadcrumb-item active">#{{ $transaction->id }}</li>
@endsection

@section('content')
@if($transaction->source === 'telegram')
<div class="alert alert-info">
    <i class="bi bi-telegram"></i> Criada via Telegram.
    @if(!$transaction->category)
        Escolha uma <strong>categoria</strong> abaixo e salve (não exige senha).
    @endif
</div>
@endif

@include('finance.transactions._receipt_scanner', [
    'scanTarget' => 'tx-edit-form',
    'scanTransactionId' => $transaction->id,
])

@if($transaction->operation_id && $transaction->type->value === 'income' && ! $transaction->linked_transaction_id)
    <div class="alert alert-warning d-flex align-items-center justify-content-between gap-2 mb-3">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-arrow-left-right fs-5 flex-shrink-0"></i>
            <div>
                <strong>Este aporte ainda não tem contraparte no capital pessoal.</strong><br>
                <small class="text-muted">Crie a despesa espelhada para representar que o dinheiro saiu do seu bolso.</small>
            </div>
        </div>
        <form method="POST" action="{{ route('transactions.mirror-personal', $transaction) }}">
            @csrf
            <button type="submit" class="btn btn-warning btn-sm text-nowrap">
                <i class="bi bi-plus-circle"></i> Registrar saída pessoal
            </button>
        </form>
    </div>
@endif

@if($transaction->linkedTransaction)
    @php $linked = $transaction->linkedTransaction; @endphp
    <div class="alert alert-info d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-arrow-left-right fs-5 flex-shrink-0"></i>
        <div>
            <strong>Lançamento double-entry</strong> —
            @if($transaction->operation_id)
                Este é o lado <strong>entrada na operação</strong>.
                A saída do capital pessoal está em
            @else
                Este é o lado <strong>saída do capital pessoal</strong>.
                A entrada na operação está em
            @endif
            <a href="{{ route('transactions.edit', $linked) }}">#{{ $linked->id }} {{ $linked->description }}</a>
            (R$ {{ number_format($linked->amount, 2, ',', '.') }} · {{ $linked->transaction_date->format('d/m/Y') }}).
            Alterações em valor, data e descrição são sincronizadas automaticamente.
        </div>
    </div>
@endif

<div class="card card-primary">
    <form action="{{ route('transactions.update', $transaction) }}" method="POST" id="tx-edit-form">
        @csrf
        @method('PUT')
        <div class="card-body">
            <div class="row">
                @include('finance.transactions._category_suggestions', [
                    'categories' => $categories,
                    'selectedCategoryId' => old('category_id', $transaction->category_id),
                ])
                <div class="col-md-6 mb-3">
                    <label class="form-label">Empresa vinculada</label>
                    <select name="company_id" class="form-select">
                        <option value="">— Nenhuma —</option>
                        @foreach($companies as $c)
                            <option value="{{ $c->id }}" @selected($transaction->company_id == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>

                @include('finance.transactions._operation_fields', [
                    'operations' => $operations,
                    'transaction' => $transaction,
                ])

                <div class="col-md-3 mb-3">
                    <label class="form-label">Tipo</label>
                    <select name="type" class="form-select sensitive-field" required>
                        <option value="expense" @selected($transaction->type->value === 'expense')>Despesa</option>
                        <option value="income" @selected($transaction->type->value === 'income')>Receita</option>
                        <option value="transfer" @selected($transaction->type->value === 'transfer')>Transferência</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Valor (R$)</label>
                    <input type="number" step="0.01" name="amount" class="form-control sensitive-field"
                        value="{{ old('amount', $transaction->amount) }}" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Data</label>
                    <input type="date" name="transaction_date" class="form-control sensitive-field"
                        value="{{ old('transaction_date', $transaction->transaction_date->format('Y-m-d')) }}" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select sensitive-field" required>
                        @foreach(['pending' => 'Pendente', 'confirmed' => 'Confirmado', 'cancelled' => 'Cancelado', 'reconciled' => 'Conciliado'] as $val => $label)
                            <option value="{{ $val }}" @selected($transaction->status->value === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Descrição</label>
                    <input type="text" name="description" class="form-control sensitive-field"
                        value="{{ old('description', $transaction->description) }}" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Contraparte</label>
                    <input type="text" name="counterparty" class="form-control"
                        value="{{ old('counterparty', $transaction->counterparty) }}">
                </div>

                @include('finance.transactions._payment_fields', ['transaction' => $transaction])
            </div>

            @if($requirePassword)
            <div class="border rounded p-3 bg-light mt-2" id="password-box" style="display:none">
                <label class="form-label fw-semibold text-danger">
                    <i class="bi bi-shield-lock"></i> Senha da conta (obrigatória para alterar valor, data, tipo ou descrição)
                </label>
                <input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror"
                    autocomplete="current-password" placeholder="Sua senha de login">
                @error('current_password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            @endif
        </div>
        <div class="card-footer d-flex justify-content-between flex-wrap gap-2">
            <div>
                <button type="submit" class="btn btn-primary">Salvar alterações</button>
                <a href="{{ route('transactions.index') }}" class="btn btn-secondary">Voltar</a>
            </div>
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteTransactionModal">
                <i class="bi bi-trash"></i> Excluir transação
            </button>
        </div>
    </form>
</div>

@include('finance.transactions._receipts', [
    'transaction' => $transaction,
    'unlinkedDocuments' => $unlinkedDocuments,
])

<div class="modal fade" id="deleteTransactionModal" tabindex="-1" aria-labelledby="deleteTransactionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('transactions.destroy', $transaction) }}" method="POST" id="tx-delete-form">
                @csrf
                @method('DELETE')
                <input type="hidden" name="delete_intent" value="1">
                <div class="modal-header border-danger">
                    <h5 class="modal-title text-danger" id="deleteTransactionModalLabel">
                        <i class="bi bi-exclamation-triangle"></i> Excluir transação #{{ $transaction->id }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2"><strong>{{ $transaction->description }}</strong></p>
                    <p class="mb-3">
                        R$ {{ number_format($transaction->amount, 2, ',', '.') }}
                        · {{ $transaction->transaction_date->format('d/m/Y') }}
                    </p>
                    <div class="alert alert-warning small mb-3">
                        <strong>Exclusão lógica:</strong> o lançamento some da lista, mas permanece no banco
                        (auditoria). Não é remoção física.
                    </div>
                    @if($transaction->linkedTransaction)
                    <div class="alert alert-danger small mb-3">
                        <i class="bi bi-arrow-left-right"></i>
                        <strong>Atenção:</strong> este lançamento tem uma contraparte double-entry (#{{ $transaction->linkedTransaction->id }}).
                        Ambos serão excluídos juntos.
                    </div>
                    @endif
                    <div class="form-check mb-3">
                        <input class="form-check-input @error('confirm_delete') is-invalid @enderror" type="checkbox"
                            name="confirm_delete" value="1" id="confirm-delete" required>
                        <label class="form-check-label" for="confirm-delete">
                            Entendo que esta transação será ocultada do sistema
                        </label>
                        @error('confirm_delete')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Confirmação (digite <code>EXCLUIR</code>)</label>
                        <input type="text" name="delete_confirmation" class="form-control @error('delete_confirmation') is-invalid @enderror"
                            autocomplete="off" placeholder="EXCLUIR" required pattern="EXCLUIR"
                            oninput="this.value = this.value.toUpperCase()">
                        @error('delete_confirmation')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Senha da conta</label>
                        <input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror"
                            autocomplete="current-password" required>
                        @error('current_password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger" id="tx-delete-submit" disabled>
                        Excluir definitivamente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const deleteModal = document.getElementById('deleteTransactionModal');
    if (deleteModal) {
        const checkbox = document.getElementById('confirm-delete');
        const phrase = deleteModal.querySelector('input[name=delete_confirmation]');
        const password = deleteModal.querySelector('input[name=current_password]');
        const submit = document.getElementById('tx-delete-submit');
        const syncDeleteBtn = () => {
            const ok = checkbox?.checked
                && phrase?.value === 'EXCLUIR'
                && (password?.value?.length ?? 0) > 0;
            if (submit) submit.disabled = !ok;
        };
        checkbox?.addEventListener('change', syncDeleteBtn);
        phrase?.addEventListener('input', syncDeleteBtn);
        password?.addEventListener('input', syncDeleteBtn);
        @if(old('delete_intent') || $errors->has('confirm_delete') || $errors->has('delete_confirmation'))
        bootstrap.Modal.getOrCreateInstance(deleteModal).show();
        syncDeleteBtn();
        @endif
    }
})();
</script>
@if($requirePassword)
<script>
(function () {
    const form = document.getElementById('tx-edit-form');
    const box = document.getElementById('password-box');
    const initial = {};
    form.querySelectorAll('.sensitive-field').forEach(el => {
        initial[el.name] = el.type === 'checkbox' ? el.checked : el.value;
    });
    const toggle = () => {
        let changed = false;
        form.querySelectorAll('.sensitive-field').forEach(el => {
            const v = el.type === 'checkbox' ? el.checked : el.value;
            if (String(v) !== String(initial[el.name])) changed = true;
        });
        box.style.display = changed ? 'block' : 'none';
        if (changed) box.querySelector('input[name=current_password]')?.setAttribute('required', 'required');
        else box.querySelector('input[name=current_password]')?.removeAttribute('required');
    };
    form.querySelectorAll('.sensitive-field').forEach(el => el.addEventListener('input', toggle));
    form.querySelectorAll('.sensitive-field').forEach(el => el.addEventListener('change', toggle));
    @if($errors->has('current_password')) box.style.display = 'block'; @endif
})();
</script>
@endif
<script>
(function () {
    const raw = sessionStorage.getItem('transaction_prefill');
    if (!raw || typeof window.applyReceiptExtract !== 'function') return;
    try {
        window.applyReceiptExtract(JSON.parse(raw));
        sessionStorage.removeItem('transaction_prefill');
        const alertEl = document.createElement('div');
        alertEl.className = 'alert alert-success alert-dismissible fade show';
        alertEl.innerHTML = '<i class="bi bi-magic"></i> Dados do comprovante aplicados no formulário. Revise e salve.'
            + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        document.querySelector('#tx-edit-form')?.closest('.card')?.before(alertEl);
    } catch (e) {}
})();
</script>
@endpush
