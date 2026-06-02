@extends('layouts.adminlte')

@section('title', 'Auto categorização')
@section('page_title', 'Regras de auto categorização')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('categories.index') }}">Categorias</a></li>
    <li class="breadcrumb-item active">Auto categorização</li>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
@endpush

@section('content')
<div class="row">
    <div class="col-lg-4 mb-3">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title">Nova regra</h3></div>
            <form action="{{ route('categorization-rules.store') }}" method="POST">
                @csrf
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="rule-names">Nomes / padrões na descrição</label>
                        <select name="names[]" id="rule-names" class="form-select" multiple required
                            data-placeholder="Digite e pressione Enter — ex.: Uber, Hotel, débito automático">
                            @foreach($suggestedNames as $suggestion)
                                <option value="{{ $suggestion }}">{{ $suggestion }}</option>
                            @endforeach
                            @foreach(old('names', []) as $oldName)
                                <option value="{{ $oldName }}" selected>{{ $oldName }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">
                            Vários nomes com a mesma categoria: cada um vira uma regra (busca na descrição/contraparte, sem diferenciar maiúsculas).
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipo de correspondência</label>
                        <select name="match_type" class="form-select">
                            @foreach($matchTypes as $value => $label)
                                <option value="{{ $value }}" @selected(old('match_type', 'contains') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Categoria</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">— Selecione —</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" @selected((int) old('category_id') === $cat->id)>
                                    {{ $cat->name }} ({{ $cat->type === 'income' ? 'Receita' : 'Despesa' }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Operação <span class="badge text-bg-secondary ms-1" style="font-size:.7rem">só deste workspace</span></label>
                        <select name="operation_id" class="form-select">
                            <option value="">— Nenhuma —</option>
                            @foreach($operations as $op)
                                <option value="{{ $op->id }}" @selected((int) old('operation_id') === $op->id)>{{ $op->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Empresa <span class="badge text-bg-secondary ms-1" style="font-size:.7rem">só deste workspace</span></label>
                        <select name="company_id" class="form-select">
                            <option value="">— Nenhuma —</option>
                            @foreach($companies as $co)
                                <option value="{{ $co->id }}" @selected((int) old('company_id') === $co->id)>{{ $co->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Aplicar só a transações</label>
                        <select name="transaction_type" class="form-select">
                            <option value="" @selected(old('transaction_type') === null || old('transaction_type') === '')>Qualquer tipo</option>
                            <option value="income" @selected(old('transaction_type') === 'income')>Receita</option>
                            <option value="expense" @selected(old('transaction_type') === 'expense')>Despesa</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Prioridade</label>
                        <input type="number" name="priority" class="form-control" value="{{ old('priority', 50) }}" min="1" max="9999" required>
                        <div class="form-text">Menor número = avaliada antes (ex.: 10 antes de 100).</div>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="rule_active" @checked(old('is_active', true))>
                        <label class="form-check-label" for="rule_active">Ativa</label>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary w-100">Adicionar regra(s)</button>
                </div>
            </form>
        </div>
        <div class="card card-outline card-info">
            <div class="card-body small">
                <strong>Exemplos</strong>
                <ul class="mb-0 ps-3">
                    <li><code>Uber</code>, <code>99</code>, <code>Cabify</code> → Transporte</li>
                    <li><code>Hotel</code>, <code>Booking</code> → Viagem</li>
                    <li><code>débito automático</code> → Contas recorrentes</li>
                </ul>
                <p class="mb-0 mt-2 text-muted">
                    Usadas ao criar transações, importar extrato e no botão “Categorizar em lote” em Transações.
                </p>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        @if($sharedSuggestions->isNotEmpty())
            <div class="card card-outline card-success">
                <div class="card-header">
                    <h3 class="card-title mb-0">Regras sugeridas de outros workspaces</h3>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Regra</th>
                                <th>Categoria local</th>
                                <th class="text-center">Matches</th>
                                <th>Exemplo</th>
                                <th class="text-end">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sharedSuggestions as $suggestion)
                                @php($rule = $suggestion['rule'])
                                <tr>
                                    <td>
                                        <span class="badge text-bg-secondary">{{ $matchTypes[$rule->match_type] ?? $rule->match_type }}</span>
                                        <code class="ms-1">{{ $rule->pattern }}</code>
                                        @if($rule->transaction_type)
                                            <span class="badge text-bg-light ms-1">{{ $rule->transaction_type }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <form id="accept-shared-rule-{{ $rule->id }}" action="{{ route('categorization-rules.shared.accept', $rule) }}" method="POST" class="d-flex gap-2">
                                            @csrf
                                            <input type="hidden" name="priority" value="{{ $rule->priority }}">
                                            <select name="category_id" class="form-select form-select-sm" required>
                                                <option value="">— Selecione —</option>
                                                @foreach($categories as $cat)
                                                    <option value="{{ $cat->id }}" @selected(optional($suggestion['suggested_category'])->id === $cat->id)>
                                                        {{ $cat->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </form>
                                    </td>
                                    <td class="text-center">{{ $suggestion['matches_count'] }}</td>
                                    <td class="text-muted">{{ Str::limit($suggestion['sample_description'], 42) }}</td>
                                    <td class="text-end">
                                        <button type="submit" form="accept-shared-rule-{{ $rule->id }}" class="btn btn-sm btn-success">
                                            Reutilizar
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer small text-muted">
                    Ao reutilizar, o workspace cria apenas um vínculo com a regra existente e escolhe a categoria local.
                </div>
            </div>
        @endif

        @if($sharedAssignments->isNotEmpty())
            <div class="card card-outline card-secondary">
                <div class="card-header">
                    <h3 class="card-title mb-0">Regras reutilizadas</h3>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Padrão</th>
                                <th>Categoria local</th>
                                <th class="text-center">Usos</th>
                                <th class="text-center">Ativa</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sharedAssignments as $assignment)
                                <tr>
                                    <td>
                                        <span class="badge text-bg-secondary">{{ $matchTypes[$assignment->rule->match_type] ?? $assignment->rule->match_type }}</span>
                                        <code class="ms-1">{{ $assignment->rule->pattern }}</code>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill text-bg-secondary">
                                            {{ $assignment->category->name }}
                                        </span>
                                    </td>
                                    <td class="text-center">{{ $assignment->hit_count }}</td>
                                    <td class="text-center">
                                        <span class="badge text-bg-{{ $assignment->is_active ? 'success' : 'secondary' }}">
                                            {{ $assignment->is_active ? 'Sim' : 'Não' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0">Regras cadastradas</h3>
                <div class="card-tools">
                    <a href="{{ route('transactions.index') }}" class="btn btn-sm btn-outline-secondary">Transações</a>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Padrão</th>
                            <th>Categoria</th>
                            <th>Operação</th>
                            <th>Empresa</th>
                            <th>Tipo tx.</th>
                            <th class="text-center">Prior.</th>
                            <th class="text-center">Usos</th>
                            <th class="text-center">Ativa</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rules as $rule)
                            <tr class="{{ $rule->is_active ? '' : 'text-muted' }}">
                                <td class="fw-semibold">{{ $rule->name ?: '—' }}</td>
                                <td>
                                    <span class="badge text-bg-secondary">{{ $matchTypes[$rule->match_type] ?? $rule->match_type }}</span>
                                    <code class="ms-1">{{ $rule->pattern }}</code>
                                </td>
                                <td>
                                    @if($rule->category)
                                        <span class="badge rounded-pill text-bg-secondary">
                                            {{ $rule->category->name }}
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="small">{{ $rule->operation?->name ?? '—' }}</td>
                                <td class="small">{{ $rule->company?->name ?? '—' }}</td>
                                <td>
                                    @if($rule->transaction_type === 'income')
                                        <span class="badge text-bg-success">Receita</span>
                                    @elseif($rule->transaction_type === 'expense')
                                        <span class="badge text-bg-danger">Despesa</span>
                                    @else
                                        <span class="text-muted">Qualquer</span>
                                    @endif
                                </td>
                                <td class="text-center">{{ $rule->priority }}</td>
                                <td class="text-center">{{ $rule->hit_count }}</td>
                                <td class="text-center">
                                    <form action="{{ route('categorization-rules.toggle', $rule) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-xs btn-sm {{ $rule->is_active ? 'btn-success' : 'btn-outline-secondary' }}" title="Ativar/desativar">
                                            <i class="bi {{ $rule->is_active ? 'bi-check-circle' : 'bi-pause-circle' }}"></i>
                                        </button>
                                    </form>
                                </td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#edit-rule-{{ $rule->id }}" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="{{ route('categorization-rules.destroy', $rule) }}" method="POST" class="d-inline" onsubmit="return confirm('Remover esta regra?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <tr class="collapse" id="edit-rule-{{ $rule->id }}">
                                <td colspan="10" class="bg-light">
                                    <form action="{{ route('categorization-rules.update', $rule) }}" method="POST" class="p-3">
                                        @csrf
                                        @method('PUT')
                                        <div class="row g-2">
                                            <div class="col-md-2">
                                                <label class="form-label small">Nome</label>
                                                <input type="text" name="name" class="form-control form-control-sm" value="{{ $rule->name }}" required>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small">Padrão</label>
                                                <input type="text" name="pattern" class="form-control form-control-sm" value="{{ $rule->pattern }}" required>
                                            </div>
                                            <div class="col-md-1">
                                                <label class="form-label small">Correspondência</label>
                                                <select name="match_type" class="form-select form-select-sm">
                                                    @foreach($matchTypes as $value => $label)
                                                        <option value="{{ $value }}" @selected($rule->match_type === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small">Categoria</label>
                                                <select name="category_id" class="form-select form-select-sm" required>
                                                    @foreach($categories as $cat)
                                                        <option value="{{ $cat->id }}" @selected($rule->category_id === $cat->id)>{{ $cat->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small">Operação <span class="text-muted">(só aqui)</span></label>
                                                <select name="operation_id" class="form-select form-select-sm">
                                                    <option value="">— Nenhuma —</option>
                                                    @foreach($operations as $op)
                                                        <option value="{{ $op->id }}" @selected($rule->operation_id === $op->id)>{{ $op->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small">Empresa <span class="text-muted">(só aqui)</span></label>
                                                <select name="company_id" class="form-select form-select-sm">
                                                    <option value="">— Nenhuma —</option>
                                                    @foreach($companies as $co)
                                                        <option value="{{ $co->id }}" @selected($rule->company_id === $co->id)>{{ $co->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-1">
                                                <label class="form-label small">Tipo tx.</label>
                                                <select name="transaction_type" class="form-select form-select-sm">
                                                    <option value="">—</option>
                                                    <option value="income" @selected($rule->transaction_type === 'income')>Rec.</option>
                                                    <option value="expense" @selected($rule->transaction_type === 'expense')>Desp.</option>
                                                </select>
                                            </div>
                                            <div class="col-md-1">
                                                <label class="form-label small">Prior.</label>
                                                <input type="number" name="priority" class="form-control form-control-sm" value="{{ $rule->priority }}" min="1" max="9999">
                                            </div>
                                        </div>
                                        <div class="mt-2 d-flex align-items-center gap-2">
                                            <div class="form-check mb-0">
                                                <input type="checkbox" name="is_active" value="1" class="form-check-input" id="active-{{ $rule->id }}" @checked($rule->is_active)>
                                                <label class="form-check-label small" for="active-{{ $rule->id }}">Ativa</label>
                                            </div>
                                            <button type="submit" class="btn btn-sm btn-primary ms-auto">Salvar</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    Nenhuma regra. Adicione exemplos como Uber ou Evento B3 para automatizar categorias.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/pt-BR.js"></script>
<script>
(function () {
    const el = document.getElementById('rule-names');
    if (!el || typeof jQuery === 'undefined') return;

    jQuery(el).select2({
        theme: 'bootstrap-5',
        language: 'pt-BR',
        width: '100%',
        tags: true,
        tokenSeparators: [',', ';'],
        placeholder: el.dataset.placeholder || 'Adicione nomes…',
        allowClear: true,
        closeOnSelect: false,
    });
})();
</script>
@endpush
