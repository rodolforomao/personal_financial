@php
    use App\Core\Enums\FundingSource;
    use App\Core\Enums\PaymentMethod;
    use App\Core\Enums\TransactionStatus;
    $skip = '__skip__';
    $clear = '__clear__';
    $unitsJson = $operations->mapWithKeys(fn ($op) => [
        $op->id => $op->activeUnits->map(fn ($u) => ['id' => $u->id, 'label' => $u->displayName()])->values(),
    ]);
@endphp

<div id="bulk-toolbar" class="card border-primary mb-3 d-none">
    <div class="card-body py-2 d-flex flex-wrap align-items-center gap-2">
        <span class="fw-semibold"><span id="bulk-selected-count">0</span> selecionada(s)</span>
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#bulkUpdateModal">
            <i class="bi bi-pencil-square"></i> Alterar em massa
        </button>
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#bulkRecurringModal">
            <i class="bi bi-arrow-repeat"></i> Recorrente
        </button>
        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#bulkDeleteModal">
            <i class="bi bi-trash"></i> Excluir
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="bulk-clear-selection">Limpar seleção</button>
    </div>
</div>

<div class="modal fade" id="bulkUpdateModal" tabindex="-1" aria-labelledby="bulkUpdateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="{{ route('transactions.bulk-update') }}" method="POST" id="bulk-update-form">
            @csrf
            <div id="bulk-update-ids-container"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkUpdateModalLabel">Alterar transações em massa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        <strong>1.</strong> Você já selecionou as linhas na tabela.<br>
                        <strong>2.</strong> Abaixo, escolha o <strong>novo valor</strong> só nos campos que quer mudar.
                        Deixe <em>“Não alterar”</em> nos demais.
                    </div>

                    <div class="mb-3 border rounded p-3">
                        <label class="form-label fw-semibold mb-1">Categoria</label>
                        <select name="category_id" class="form-select form-select-sm bulk-field-select">
                            <option value="{{ $skip }}" selected>— Não alterar —</option>
                            <option value="{{ $clear }}">— Remover categoria —</option>
                            @foreach($categories->groupBy('type') as $type => $group)
                                <optgroup label="{{ $type === 'income' ? 'Receitas' : 'Despesas' }}">
                                    @foreach($group as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3 border rounded p-3">
                        <label class="form-label fw-semibold mb-1">Empresa</label>
                        <select name="company_id" class="form-select form-select-sm bulk-field-select">
                            <option value="{{ $skip }}" selected>— Não alterar —</option>
                            <option value="{{ $clear }}">— Remover empresa —</option>
                            @foreach($companies as $c)
                                <option value="{{ $c->id }}">[{{ $c->type->label() }}] {{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3 border rounded p-3">
                        <label class="form-label fw-semibold mb-1">Operação / unidade</label>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <select name="operation_id" id="bulk-operation-id" class="form-select form-select-sm bulk-field-select">
                                    <option value="{{ $skip }}" selected>— Não alterar —</option>
                                    <option value="{{ $clear }}">— Sem operação (dashboard geral) —</option>
                                    @foreach($operations as $op)
                                        <option value="{{ $op->id }}">{{ $op->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <select name="operation_unit_id" id="bulk-operation-unit-id" class="form-select form-select-sm bulk-field-select" disabled>
                                    <option value="{{ $skip }}" selected>— Não alterar unidade —</option>
                                    <option value="{{ $clear }}">— Operação inteira —</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6 border rounded p-3">
                            <label class="form-label fw-semibold mb-1">Fonte do recurso</label>
                            <select name="funding_source" class="form-select form-select-sm bulk-field-select">
                                <option value="{{ $skip }}" selected>— Não alterar —</option>
                                <option value="{{ $clear }}">— Remover —</option>
                                @foreach(FundingSource::cases() as $source)
                                    <option value="{{ $source->value }}">{{ $source->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 border rounded p-3">
                            <label class="form-label fw-semibold mb-1">Meio de pagamento</label>
                            <select name="payment_method" class="form-select form-select-sm bulk-field-select">
                                <option value="{{ $skip }}" selected>— Não alterar —</option>
                                <option value="{{ $clear }}">— Remover —</option>
                                @foreach(PaymentMethod::cases() as $method)
                                    <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mt-3 border rounded p-3">
                        <label class="form-label fw-semibold mb-1">Status</label>
                        <select name="status" class="form-select form-select-sm bulk-field-select">
                            <option value="{{ $skip }}" selected>— Não alterar —</option>
                            @foreach(TransactionStatus::cases() as $st)
                                <option value="{{ $st->value }}">{{ ucfirst($st->value) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div id="bulk-update-hint" class="alert alert-warning d-none small mt-3 mb-0">
                        Escolha ao menos um campo diferente de “Não alterar”.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="bulk-update-submit">Aplicar nas selecionadas</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="bulkRecurringModal" tabindex="-1" aria-labelledby="bulkRecurringModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('transactions.bulk-mark-recurring') }}" method="POST" id="bulk-recurring-form">
            @csrf
            <div id="bulk-recurring-ids-container"></div>
            <div class="modal-content border-primary">
                <div class="modal-header border-primary">
                    <h5 class="modal-title" id="bulkRecurringModalLabel">
                        <i class="bi bi-arrow-repeat"></i> Marcar como recorrente mensal
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>
                        Serão marcadas como <strong>recorrentes mensais</strong> as
                        <strong id="bulk-recurring-count">0</strong> transação(ões) selecionada(s).
                    </p>
                    <div class="alert alert-info small mb-3">
                        <i class="bi bi-info-circle"></i>
                        Para cada receita ainda não vinculada, um registro de recorrência será criado automaticamente
                        usando a descrição, valor e empresa da transação.
                        Transações que não são do tipo <em>receita</em> ou que já possuem vínculo serão ignoradas.
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="alert_enabled" value="1"
                               id="bulk-recurring-alert" checked>
                        <label class="form-check-label" for="bulk-recurring-alert">
                            <i class="bi bi-bell"></i> Ativar alerta mensal para as receitas criadas
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-arrow-repeat"></i> Marcar como recorrente
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-labelledby="bulkDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('transactions.bulk-destroy') }}" method="POST" id="bulk-delete-form">
            @csrf
            <div id="bulk-delete-ids-container"></div>
            <div class="modal-content border-danger">
                <div class="modal-header border-danger">
                    <h5 class="modal-title text-danger" id="bulkDeleteModalLabel">Excluir transações</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Exclusão lógica de <strong id="bulk-delete-count">0</strong> lançamento(s). Digite <code>EXCLUIR</code> e sua senha.</p>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="confirm_delete" value="1" id="bulk_confirm_delete" required>
                        <label class="form-check-label" for="bulk_confirm_delete">Entendo que os lançamentos serão ocultados</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirmação</label>
                        <input type="text" name="delete_confirmation" class="form-control" placeholder="EXCLUIR" required autocomplete="off">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Senha da conta</label>
                        <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Excluir selecionadas</button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const SKIP = @json($skip);
    const CLEAR = @json($clear);
    const unitsByOperation = @json($unitsJson);
    const toolbar = document.getElementById('bulk-toolbar');
    const countEl = document.getElementById('bulk-selected-count');
    const deleteCountEl = document.getElementById('bulk-delete-count');
    const checkAll = document.getElementById('tx-check-all');
    const boxes = () => Array.from(document.querySelectorAll('.tx-bulk-check'));

    function selectedIds() {
        return boxes().filter(b => b.checked).map(b => b.value);
    }

    function syncToolbar() {
        const ids = selectedIds();
        const n = ids.length;
        if (countEl) countEl.textContent = n;
        if (deleteCountEl) deleteCountEl.textContent = n;
        toolbar?.classList.toggle('d-none', n === 0);
        if (checkAll) checkAll.checked = n > 0 && n === boxes().length;
        if (checkAll) checkAll.indeterminate = n > 0 && n < boxes().length;
    }

    function injectIds(containerId, ids) {
        const c = document.getElementById(containerId);
        if (!c) return;
        c.innerHTML = '';
        ids.forEach(id => {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'ids[]';
            inp.value = id;
            c.appendChild(inp);
        });
    }

    function isFieldActive(select) {
        return select && select.value !== '' && select.value !== SKIP;
    }

    function countActiveBulkFields(form) {
        return Array.from(form.querySelectorAll('.bulk-field-select')).filter(isFieldActive).length;
    }

    boxes().forEach(b => b.addEventListener('change', syncToolbar));
    checkAll?.addEventListener('change', function () {
        boxes().forEach(b => { b.checked = checkAll.checked; });
        syncToolbar();
    });
    document.getElementById('bulk-clear-selection')?.addEventListener('click', function () {
        boxes().forEach(b => { b.checked = false; });
        if (checkAll) checkAll.checked = false;
        syncToolbar();
    });

    const bulkForm = document.getElementById('bulk-update-form');
    const bulkHint = document.getElementById('bulk-update-hint');

    bulkForm?.addEventListener('submit', function (e) {
        injectIds('bulk-update-ids-container', selectedIds());
        const ids = selectedIds();
        if (ids.length === 0) {
            e.preventDefault();
            alert('Selecione ao menos uma transação na tabela.');
            return;
        }
        if (countActiveBulkFields(this) === 0) {
            e.preventDefault();
            bulkHint?.classList.remove('d-none');
            return;
        }
        bulkHint?.classList.add('d-none');
        if (!confirm('Aplicar alterações em ' + ids.length + ' transação(ões)?')) {
            e.preventDefault();
        }
    });

    bulkForm?.querySelectorAll('.bulk-field-select').forEach(sel => {
        sel.addEventListener('change', () => bulkHint?.classList.add('d-none'));
    });

    document.getElementById('bulk-delete-form')?.addEventListener('submit', function (e) {
        injectIds('bulk-delete-ids-container', selectedIds());
        if (selectedIds().length === 0) {
            e.preventDefault();
            alert('Selecione ao menos uma transação na tabela.');
        }
    });

    const bulkRecurringModal = document.getElementById('bulkRecurringModal');
    const bulkRecurringCount = document.getElementById('bulk-recurring-count');
    bulkRecurringModal?.addEventListener('show.bs.modal', function () {
        const ids = selectedIds();
        if (bulkRecurringCount) bulkRecurringCount.textContent = ids.length;
    });
    document.getElementById('bulk-recurring-form')?.addEventListener('submit', function (e) {
        injectIds('bulk-recurring-ids-container', selectedIds());
        if (selectedIds().length === 0) {
            e.preventDefault();
            alert('Selecione ao menos uma transação na tabela.');
        }
    });

    const bulkOp = document.getElementById('bulk-operation-id');
    const bulkUnit = document.getElementById('bulk-operation-unit-id');

    function refreshBulkUnits() {
        if (!bulkUnit) return;
        const opVal = bulkOp?.value;
        const opActive = isFieldActive(bulkOp);
        bulkUnit.disabled = !opActive || opVal === SKIP;
        if (!opActive || opVal === SKIP) {
            bulkUnit.value = SKIP;
            return;
        }
        if (opVal === CLEAR) {
            bulkUnit.disabled = false;
            bulkUnit.innerHTML = '<option value="'+SKIP+'">— Não alterar unidade —</option><option value="'+CLEAR+'" selected>— Operação inteira —</option>';
            return;
        }
        bulkUnit.disabled = false;
        bulkUnit.innerHTML = '<option value="'+SKIP+'" selected>— Não alterar unidade —</option><option value="'+CLEAR+'">— Operação inteira —</option>';
        if (unitsByOperation[opVal]) {
            unitsByOperation[opVal].forEach(u => {
                const opt = document.createElement('option');
                opt.value = u.id;
                opt.textContent = u.label;
                bulkUnit.appendChild(opt);
            });
        }
    }

    bulkOp?.addEventListener('change', refreshBulkUnits);
    refreshBulkUnits();
})();
</script>
@endpush
