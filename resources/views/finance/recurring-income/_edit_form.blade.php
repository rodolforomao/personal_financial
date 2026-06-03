{{-- Partial: formulário de edição inline de um RecurringItem --}}
<form action="{{ route('recurring-income.update', $item) }}" method="POST">
    @csrf
    @method('PUT')
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small">Nome</label>
            <input type="text" name="title" class="form-control form-control-sm"
                   value="{{ $item->title }}" required>
        </div>

        {{-- Índice --}}
        <div class="col-md-3">
            <label class="form-label small">Base de cálculo</label>
            <select name="amount_index" class="form-select form-select-sm edit-amount-index"
                    data-item="{{ $item->id }}">
                <option value="">— Valor fixo —</option>
                @foreach($indexLabels as $key => $label)
                    <option value="{{ $key }}" @selected($item->amount_index === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        {{-- Factor (condicional) --}}
        <div class="col-md-2 edit-factor-col-{{ $item->id }}" style="{{ $item->amount_index ? '' : 'display:none' }}">
            <label class="form-label small edit-factor-label-{{ $item->id }}">
                {{ $item->amount_index === 'salario_minimo' ? 'Percentual' : ($item->amount_index === 'usdt' ? 'Qtd USDT' : 'Fator') }}
            </label>
            <input type="number" name="amount_factor" step="0.000001" min="0.000001"
                   class="form-control form-control-sm"
                   value="{{ $item->amount_factor }}"
                   placeholder="{{ $item->amount_index === 'usdt' ? '100' : '0.0175' }}">
        </div>

        <div class="col-md-2">
            <label class="form-label small">Valor ref. (R$)</label>
            <input type="number" name="amount" step="0.01" min="0.01"
                   class="form-control form-control-sm" value="{{ $item->amount }}" required>
        </div>
        <div class="col-md-2">
            <label class="form-label small">Dia do mês</label>
            <input type="number" name="day_of_month" min="1" max="28"
                   class="form-control form-control-sm" value="{{ $item->day_of_month }}">
        </div>
        <div class="col-md-2">
            <label class="form-label small">Próx. vencimento</label>
            <input type="date" name="next_due_at" class="form-control form-control-sm"
                   value="{{ $item->next_due_at->toDateString() }}" required>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Empresa</label>
            <select name="company_id" class="form-select form-select-sm">
                <option value="">— Nenhuma —</option>
                @foreach($companies as $c)
                    <option value="{{ $c->id }}" @selected($item->company_id == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Categoria</label>
            <select name="category_id" class="form-select form-select-sm">
                <option value="">— Nenhuma —</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected($item->category_id == $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" name="alert_enabled" value="1"
                       id="alert-{{ $item->id }}" @checked($item->alert_enabled)>
                <label class="form-check-label small" for="alert-{{ $item->id }}">Alerta</label>
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" name="is_active" value="1"
                       id="active-{{ $item->id }}" @checked($item->is_active)>
                <label class="form-check-label small" for="active-{{ $item->id }}">Ativa</label>
            </div>
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Salvar</button>
        </div>
    </div>
</form>
<form action="{{ route('recurring-income.destroy', $item) }}" method="POST" class="mt-2 d-inline"
      onsubmit="return confirm('Remover {{ $item->title }}?')">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-sm btn-outline-danger">
        <i class="bi bi-trash"></i> Remover
    </button>
</form>

@push('scripts')
<script>
(function () {
    const factorMeta = {
        salario_minimo: { label: 'Percentual (decimal)', placeholder: '0.0175' },
        usdt:           { label: 'Qtd USDT', placeholder: '100' },
    };
    const sel = document.querySelector('.edit-amount-index[data-item="{{ $item->id }}"]');
    if (!sel) return;
    sel.addEventListener('change', function () {
        const col   = document.querySelector('.edit-factor-col-{{ $item->id }}');
        const label = document.querySelector('.edit-factor-label-{{ $item->id }}');
        const meta  = factorMeta[this.value];
        if (meta) {
            col.style.display = '';
            label.textContent = meta.label;
            col.querySelector('input').placeholder = meta.placeholder;
        } else {
            col.style.display = 'none';
        }
    });
})();
</script>
@endpush
