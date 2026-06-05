@extends('layouts.adminlte')

@section('title', 'Receitas Recorrentes')
@section('page_title', 'Receitas Recorrentes')
@section('breadcrumb')
    <li class="breadcrumb-item active">Receitas Recorrentes</li>
@endsection

@section('content')

<p class="text-muted">
    Cadastre aqui as receitas mensais que você espera receber (aluguéis, mensalidades, contratos fixos, etc.).
    O sistema gera um alerta automático quando uma receita não chega até 3 dias após o vencimento.
    Para itens indexados (Salário Mínimo, USDT), o valor é calculado na hora da confirmação.
</p>

@php
    $today         = now();
    $overdueItems  = $items->filter(fn($i) => $i->is_active && $i->next_due_at->lt($today));
    $upcomingItems = $items->filter(fn($i) => $i->is_active && $i->next_due_at->gte($today));
    $inactiveItems = $items->filter(fn($i) => !$i->is_active);
@endphp

{{-- ── Aguardando confirmação ──────────────────────────────────────────────── --}}
@if($overdueItems->isNotEmpty())
<div class="card card-outline card-danger mb-4">
    <div class="card-header">
        <h3 class="card-title"><i class="bi bi-exclamation-triangle-fill text-danger"></i> Aguardando confirmação</h3>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>Receita</th>
                    <th>Empresa / pagador</th>
                    <th class="text-end">Valor esperado</th>
                    <th>Venceu em</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($overdueItems as $item)
                @php $calc = $calculated[$item->id] ?? null; @endphp
                <tr>
                    <td>
                        <strong>{{ $item->title }}</strong>
                        @if($item->category)
                            <span class="badge text-bg-secondary ms-1">{{ $item->category->name }}</span>
                        @endif
                    </td>
                    <td>{{ $item->company?->name ?? '—' }}</td>
                    <td class="text-end text-success fw-semibold">
                        @if($calc)
                            R$ {{ number_format($calc['amount'], 2, ',', '.') }}
                            <br><small class="text-muted fw-normal">{{ $calc['formula'] }}</small>
                        @else
                            R$ {{ number_format($item->amount, 2, ',', '.') }}
                        @endif
                    </td>
                    <td>
                        <span class="text-danger">{{ $item->next_due_at->format('d/m/Y') }}</span>
                        <small class="text-muted">({{ $item->next_due_at->diffForHumans() }})</small>
                    </td>
                    <td class="text-end">
                        @if($item->hasIndexedAmount())
                            <button type="button" class="btn btn-sm btn-success"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#confirm-{{ $item->id }}">
                                <i class="bi bi-check-lg"></i> Confirmar recebimento
                            </button>
                        @else
                            <form method="POST" action="{{ route('recurring-income.mark-received', $item) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success">
                                    <i class="bi bi-check-lg"></i> Marcar como recebida
                                </button>
                            </form>
                        @endif
                    </td>
                </tr>
                {{-- Form inline de confirmação para itens indexados --}}
                @if($item->hasIndexedAmount())
                <tr class="collapse" id="confirm-{{ $item->id }}">
                    <td colspan="5" class="bg-light p-3">
                        <form method="POST" action="{{ route('recurring-income.mark-received', $item) }}">
                            @csrf
                            <div class="row g-2 align-items-end">
                                <div class="col-12 mb-1">
                                    <span class="badge text-bg-info">
                                        {{ \App\Core\Support\IndexValueService::AVAILABLE[$item->amount_index] ?? $item->amount_index }}
                                    </span>
                                    @if($calc)
                                        <span class="small ms-2">
                                            Cálculo atual: <strong>{{ $calc['formula'] }}</strong>
                                            @if($calc['source'] === 'fallback')
                                                <span class="badge text-bg-warning ms-1">valor de referência (API indisponível)</span>
                                            @endif
                                        </span>
                                    @endif
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Valor recebido (R$)</label>
                                    <input type="number" name="amount" step="0.01" min="0.01"
                                           class="form-control form-control-sm @error('amount') is-invalid @enderror"
                                           value="{{ old('amount', $calc ? $calc['amount'] : $item->amount) }}"
                                           required>
                                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold">Data do recebimento</label>
                                    <input type="date" name="transaction_date"
                                           class="form-control form-control-sm"
                                           value="{{ now()->toDateString() }}">
                                </div>
                                <div class="col-md-5 d-flex gap-2 align-items-end">
                                    <button type="submit" class="btn btn-sm btn-success">
                                        <i class="bi bi-check-circle"></i> Confirmar e criar lançamento
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#confirm-{{ $item->id }}">
                                        Cancelar
                                    </button>
                                </div>
                            </div>
                            <small class="text-muted mt-2 d-block">
                                <i class="bi bi-info-circle"></i>
                                Um lançamento de receita será criado com este valor e você será redirecionado para editá-lo.
                            </small>
                        </form>
                    </td>
                </tr>
                @endif
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="row">
    {{-- ── Cadastrar ────────────────────────────────────────────────────────── --}}
    <div class="col-lg-5">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title">Cadastrar receita recorrente</h3></div>
            <form action="{{ route('recurring-income.store') }}" method="POST" id="new-ri-form">
                @csrf
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nome / descrição</label>
                        <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                               value="{{ old('title') }}" required placeholder="Ex.: Aluguel Apto 101, Mensalidade XYZ">
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Índice (opcional) --}}
                    <div class="mb-2">
                        <label class="form-label fw-semibold">Base de cálculo <span class="text-muted">(opcional)</span></label>
                        <select name="amount_index" id="new-amount-index" class="form-select form-select-sm">
                            <option value="">— Valor fixo em R$ —</option>
                            @foreach($indexLabels as $key => $label)
                                <option value="{{ $key }}" @selected(old('amount_index') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Ao escolher um índice, o valor é calculado na hora da confirmação.</small>
                    </div>

                    {{-- Factor (aparece quando índice selecionado) --}}
                    <div id="new-factor-row" class="mb-3" style="{{ old('amount_index') ? '' : 'display:none' }}">
                        <label class="form-label fw-semibold" id="new-factor-label">Percentual / quantidade</label>
                        <input type="number" name="amount_factor" id="new-amount-factor" step="0.000001" min="0.000001"
                               class="form-control @error('amount_factor') is-invalid @enderror"
                               value="{{ old('amount_factor') }}"
                               placeholder="Ex.: 0.0175 para 1,75%">
                        <div id="new-factor-help" class="form-text text-muted"></div>
                        @error('amount_factor')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold" id="new-amount-label">Valor esperado (R$)</label>
                            <input type="number" name="amount" step="0.01" min="0.01"
                                   class="form-control @error('amount') is-invalid @enderror"
                                   value="{{ old('amount') }}" required placeholder="0,00">
                            <small class="text-muted" id="new-amount-help"></small>
                            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Dia do mês (1–28)</label>
                            <input type="number" name="day_of_month" min="1" max="28"
                                   class="form-control @error('day_of_month') is-invalid @enderror"
                                   value="{{ old('day_of_month') }}" placeholder="Ex.: 5">
                            <small class="text-muted">Usado para recalcular o próximo vencimento</small>
                            @error('day_of_month')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Próximo vencimento</label>
                        <input type="date" name="next_due_at"
                               class="form-control @error('next_due_at') is-invalid @enderror"
                               value="{{ old('next_due_at', now()->toDateString()) }}" required>
                        @error('next_due_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Empresa / pagador <span class="text-muted">(opcional)</span></label>
                        <select name="company_id" class="form-select">
                            <option value="">— Nenhuma —</option>
                            @foreach($companies as $c)
                                <option value="{{ $c->id }}" @selected(old('company_id') == $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Categoria <span class="text-muted">(opcional)</span></label>
                        <select name="category_id" class="form-select">
                            <option value="">— Nenhuma —</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" @selected(old('category_id') == $cat->id)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="alert_enabled" value="1"
                               id="alert-enabled-new" @checked(old('alert_enabled', true))>
                        <label class="form-check-label" for="alert-enabled-new">
                            <i class="bi bi-bell"></i> Gerar alerta se não receber no prazo
                        </label>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">Cadastrar</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Lista de itens cadastrados ──────────────────────────────────────── --}}
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Receitas cadastradas</h3></div>
            <div class="card-body table-responsive p-0">
                <table id="dt-recurring-income" class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Receita</th>
                            <th>Pagador</th>
                            <th class="text-end">Valor</th>
                            <th>Próximo vencimento</th>
                            <th>Alerta</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($upcomingItems as $item)
                        @php $calc = $calculated[$item->id] ?? null; @endphp
                        <tr>
                            <td>
                                <strong>{{ $item->title }}</strong>
                                @if($item->hasIndexedAmount())
                                    <span class="badge text-bg-info ms-1">
                                        {{ \App\Core\Support\IndexValueService::AVAILABLE[$item->amount_index] ?? $item->amount_index }}
                                    </span>
                                @endif
                                @if($item->category)
                                    <br><span class="badge text-bg-secondary">{{ $item->category->name }}</span>
                                @endif
                            </td>
                            <td class="small">{{ $item->company?->name ?? '—' }}</td>
                            <td class="text-end text-success fw-semibold">
                                @if($calc)
                                    R$ {{ number_format($calc['amount'], 2, ',', '.') }}
                                    <br><small class="text-muted fw-normal" title="{{ $calc['formula'] }}">
                                        {{ \App\Core\Support\IndexValueService::AVAILABLE[$item->amount_index] ?? '' }}
                                    </small>
                                @else
                                    R$ {{ number_format($item->amount, 2, ',', '.') }}
                                @endif
                            </td>
                            <td class="small">
                                {{ $item->next_due_at->format('d/m/Y') }}
                                @if($item->next_due_at->diffInDays($today, false) >= -7 && $item->next_due_at->gte($today))
                                    <span class="badge text-bg-warning">em breve</span>
                                @endif
                                @if($item->last_occurrence_at)
                                    <br><span class="text-muted">Último: {{ $item->last_occurrence_at->format('d/m/Y') }}</span>
                                @endif
                            </td>
                            <td>
                                @if($item->alert_enabled)
                                    <span class="badge text-bg-success"><i class="bi bi-bell-fill"></i> ativo</span>
                                @else
                                    <span class="badge text-bg-secondary"><i class="bi bi-bell-slash"></i> inativo</span>
                                @endif
                            </td>
                            <td class="text-end d-flex gap-1 justify-content-end">
                                <form method="POST" action="{{ route('recurring-income.mark-received', $item) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-outline-success btn-sm"
                                            title="Marcar como recebida e avançar vencimento">
                                        <i class="bi bi-check"></i> Recebida
                                    </button>
                                </form>
                                <button type="button" class="btn btn-xs btn-outline-secondary btn-sm"
                                        data-bs-toggle="collapse" data-bs-target="#edit-ri-{{ $item->id }}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </td>
                        </tr>
                        <tr class="collapse" id="edit-ri-{{ $item->id }}">
                            <td colspan="6" class="bg-light p-3">
                                @include('finance.recurring-income._edit_form', ['item' => $item, 'companies' => $companies, 'categories' => $categories, 'indexLabels' => $indexLabels])
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma receita recorrente ativa.</td></tr>
                        @endforelse

                        @foreach($inactiveItems as $item)
                        <tr class="table-secondary opacity-75">
                            <td>
                                <span class="text-muted">{{ $item->title }}</span>
                                <span class="badge text-bg-secondary ms-1">inativa</span>
                            </td>
                            <td class="small text-muted">{{ $item->company?->name ?? '—' }}</td>
                            <td class="text-end text-muted">R$ {{ number_format($item->amount, 2, ',', '.') }}</td>
                            <td class="small text-muted">—</td>
                            <td>—</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-xs btn-outline-secondary btn-sm"
                                        data-bs-toggle="collapse" data-bs-target="#edit-ri-{{ $item->id }}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </td>
                        </tr>
                        <tr class="collapse" id="edit-ri-{{ $item->id }}">
                            <td colspan="6" class="bg-light p-3">
                                @include('finance.recurring-income._edit_form', ['item' => $item, 'companies' => $companies, 'categories' => $categories, 'indexLabels' => $indexLabels])
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="alert alert-info small mt-3 mb-0">
            <i class="bi bi-info-circle"></i>
            <strong>Receitas indexadas</strong> (Salário Mínimo, USDT): o valor é buscado em tempo real no momento
            da confirmação. O campo "Valor esperado" é atualizado automaticamente após cada recebimento e serve
            como referência para o alerta mensal.
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
new DataTable('#dt-recurring-income', {
    searching: true,
    pageLength: 25,
    lengthMenu: [[10, 25, 50, 100, 250, -1], ['10', '25', '50', '100', '250', 'Todos']],
    language: { url: 'https://cdn.datatables.net/plug-ins/2.0.8/i18n/pt-BR.json' },
    columnDefs: [{ targets: -1, orderable: false, searchable: false }],
});
</script>
<script>
(function () {
    const indexMeta = {
        salario_minimo: {
            factorLabel: 'Percentual do Salário Mínimo',
            factorHelp: 'Ex.: 0.0175 para 1,75%. O sistema calcula automaticamente.',
            factorPlaceholder: '0.0175',
            amountLabel: 'Valor de referência (R$)',
            amountHelp: 'Valor aproximado — será recalculado na hora da confirmação.',
        },
        usdt: {
            factorLabel: 'Quantidade de USDT',
            factorHelp: 'Ex.: 100 para 100 USDT. O sistema converte pela cotação Binance.',
            factorPlaceholder: '100',
            amountLabel: 'Valor de referência (R$)',
            amountHelp: 'Valor aproximado — será recalculado na hora da confirmação.',
        },
    };

    function applyIndexMeta(indexSel, factorRow, factorLabel, factorHelp, factorInput, amountLabel, amountHelp) {
        const meta = indexMeta[indexSel.value];
        if (meta) {
            factorRow.style.display = '';
            factorLabel.textContent = meta.factorLabel;
            factorHelp.textContent = meta.factorHelp;
            factorInput.placeholder = meta.factorPlaceholder;
            amountLabel.textContent = meta.amountLabel;
            amountHelp.textContent = meta.amountHelp;
        } else {
            factorRow.style.display = 'none';
            amountLabel.textContent = 'Valor esperado (R$)';
            amountHelp.textContent = '';
        }
    }

    // Formulário de novo item
    const newIdxSel = document.getElementById('new-amount-index');
    if (newIdxSel) {
        newIdxSel.addEventListener('change', function () {
            applyIndexMeta(
                this,
                document.getElementById('new-factor-row'),
                document.getElementById('new-factor-label'),
                document.getElementById('new-factor-help'),
                document.getElementById('new-amount-factor'),
                document.getElementById('new-amount-label'),
                document.querySelector('#new-ri-form #new-amount-help'),
            );
        });
    }
})();
</script>
@endpush
