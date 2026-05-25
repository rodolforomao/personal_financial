@extends('layouts.adminlte')

@section('title', 'Editar empresa')
@section('page_title', 'Editar: '.$company->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('companies.index') }}">Empresas</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
<div class="card">
    <form action="{{ route('companies.update', $company) }}" method="POST" id="company-form">
        @csrf
        @method('PUT')
        <div class="card-body row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Nome</label>
                <input type="text" name="name" class="form-control sensitive-field"
                    value="{{ old('name', $company->name) }}" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Tipo de vínculo</label>
                <select name="type" id="company-type" class="form-select sensitive-field" required>
                    @foreach($types as $type)
                        <option value="{{ $type->value }}" data-desc="{{ $type->description() }}"
                            @selected(old('type', $company->type->value) === $type->value)>
                            {{ $type->label() }}
                        </option>
                    @endforeach
                    @if(!in_array($company->type->value, array_map(fn ($t) => $t->value, $types), true))
                        <option value="{{ $company->type->value }}" selected data-desc="">
                            {{ $company->type->label() }} (legado)
                        </option>
                    @endif
                </select>
                <small class="text-muted" id="type-hint"></small>
            </div>
            <div class="col-md-6 mb-3" id="share-field">
                <label class="form-label">Participação societária (%)</label>
                <input type="number" step="0.01" name="partnership_share" class="form-control sensitive-field"
                    min="0" max="100" value="{{ old('partnership_share', $company->partnership_share) }}">
            </div>
            <div class="col-md-6 mb-3" id="revenue-field">
                <label class="form-label">Receita mensal esperada (R$)</label>
                <input type="number" step="0.01" name="expected_monthly_revenue" class="form-control sensitive-field"
                    value="{{ old('expected_monthly_revenue', $company->expected_monthly_revenue) }}">
            </div>
            @if(in_array($company->type->value, ['employer', 'payer', 'client'], true))
            <div class="col-12 mb-3">
                <div class="alert alert-info py-2 mb-0">
                    <i class="bi bi-briefcase"></i> Vínculo CLT (salário mensal com líquido variável)?
                    <a href="{{ route('clt-salaries.index') }}" class="alert-link">Configurar em Salário CLT</a>
                    — use tipo <strong>Empregador</strong> e categoria <em>Salário CLT</em> (não “Outras receitas”).
                </div>
            </div>
            @endif
            <div class="col-12 mb-3">
                <label class="form-label">Observações</label>
                <textarea name="notes" class="form-control" rows="3">{{ old('notes', $company->notes) }}</textarea>
                <small class="text-muted">Alterar só observações não exige senha.</small>
            </div>

            @if($requirePassword)
            <div class="col-12">
                <div class="border rounded p-3 bg-light" id="password-box" style="display:none">
                    <label class="form-label fw-semibold text-danger">
                        <i class="bi bi-shield-lock"></i> Senha (obrigatória ao alterar nome, tipo ou valores)
                    </label>
                    <input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror"
                        autocomplete="current-password">
                    @error('current_password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            @endif
        </div>
        <div class="card-footer d-flex justify-content-between flex-wrap gap-2">
            <div>
                <button type="submit" class="btn btn-primary">Salvar</button>
                <a href="{{ route('companies.index') }}" class="btn btn-secondary">Voltar</a>
            </div>
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteCompanyModal">
                <i class="bi bi-trash"></i> Excluir empresa
            </button>
        </div>
    </form>
</div>

<div class="modal fade" id="deleteCompanyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('companies.destroy', $company) }}" method="POST">
                @csrf
                @method('DELETE')
                <input type="hidden" name="delete_intent" value="1">
                <div class="modal-header border-danger">
                    <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle"></i> Excluir empresa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2"><strong>{{ $company->name }}</strong> · {{ $company->type->label() }}</p>
                    @if($transactionCount > 0)
                        <div class="alert alert-danger small">
                            Há <strong>{{ $transactionCount }}</strong> transação(ões) vinculadas. A empresa será ocultada;
                            os lançamentos permanecem no histórico.
                        </div>
                    @endif
                    <div class="alert alert-warning small">
                        <strong>Exclusão lógica:</strong> some da lista, mas permanece no banco para auditoria.
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input @error('confirm_delete') is-invalid @enderror" type="checkbox"
                            name="confirm_delete" value="1" id="confirm-delete-company" required>
                        <label class="form-check-label" for="confirm-delete-company">
                            Entendo que esta empresa será ocultada
                        </label>
                        @error('confirm_delete')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Digite <code>EXCLUIR</code></label>
                        <input type="text" name="delete_confirmation" class="form-control @error('delete_confirmation') is-invalid @enderror"
                            required placeholder="EXCLUIR" oninput="this.value = this.value.toUpperCase()">
                        @error('delete_confirmation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Senha da conta</label>
                        <input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" required>
                        @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger" id="company-delete-submit" disabled>Excluir definitivamente</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const typeSelect = document.getElementById('company-type');
    const syncTypeFields = () => {
        const opt = typeSelect.selectedOptions[0];
        document.getElementById('type-hint').textContent = opt?.dataset?.desc || '';
        document.getElementById('share-field').style.display = typeSelect.value === 'partner' ? '' : 'none';
        document.getElementById('revenue-field').style.display = ['payer', 'own'].includes(typeSelect.value) ? '' : 'none';
    };
    typeSelect.addEventListener('change', syncTypeFields);
    syncTypeFields();

    const modal = document.getElementById('deleteCompanyModal');
    if (modal) {
        const cb = document.getElementById('confirm-delete-company');
        const phrase = modal.querySelector('input[name=delete_confirmation]');
        const pwd = modal.querySelector('input[name=current_password]');
        const btn = document.getElementById('company-delete-submit');
        const sync = () => { btn.disabled = !(cb.checked && phrase.value === 'EXCLUIR' && pwd.value.length > 0); };
        cb.addEventListener('change', sync);
        phrase.addEventListener('input', sync);
        pwd.addEventListener('input', sync);
        @if(old('delete_intent') || $errors->has('confirm_delete') || $errors->has('delete_confirmation'))
        bootstrap.Modal.getOrCreateInstance(modal).show();
        sync();
        @endif
    }

    @if($requirePassword)
    const form = document.getElementById('company-form');
    const box = document.getElementById('password-box');
    const initial = {};
    form.querySelectorAll('.sensitive-field').forEach(el => { initial[el.name] = el.value; });
    const togglePwd = () => {
        let changed = false;
        form.querySelectorAll('.sensitive-field').forEach(el => {
            if (String(el.value) !== String(initial[el.name])) changed = true;
        });
        box.style.display = changed ? 'block' : 'none';
        const inp = box.querySelector('input[name=current_password]');
        if (changed) inp?.setAttribute('required', 'required');
        else inp?.removeAttribute('required');
    };
    form.querySelectorAll('.sensitive-field').forEach(el => {
        el.addEventListener('input', togglePwd);
        el.addEventListener('change', togglePwd);
    });
    @if($errors->has('current_password')) box.style.display = 'block'; @endif
    @endif
})();
</script>
@endpush
