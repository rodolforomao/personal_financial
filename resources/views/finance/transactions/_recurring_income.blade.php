@if($transaction->type->value === 'income')
<div class="card card-outline card-{{ $transaction->recurringItem ? 'success' : 'secondary' }} mt-3">
    <div class="card-header">
        <h3 class="card-title"><i class="bi bi-arrow-repeat"></i> Receita recorrente mensal</h3>
    </div>
    <div class="card-body">

        @if($transaction->recurringItem)
            @php $item = $transaction->recurringItem; @endphp
            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                <div>
                    <p class="mb-1">
                        <strong>{{ $item->title }}</strong>
                        @if($item->alert_enabled)
                            <span class="badge text-bg-success ms-1"><i class="bi bi-bell-fill"></i> alerta ativo</span>
                        @else
                            <span class="badge text-bg-secondary ms-1"><i class="bi bi-bell-slash"></i> alerta inativo</span>
                        @endif
                        @unless($item->is_active)
                            <span class="badge text-bg-warning ms-1">inativa</span>
                        @endunless
                    </p>
                    <p class="mb-1 text-muted small">
                        Valor esperado: <strong>R$ {{ number_format($item->amount, 2, ',', '.') }}</strong>
                        @if($item->day_of_month) · dia {{ $item->day_of_month }} de cada mês @endif
                        @if($item->company) · Pagador: {{ $item->company->name }} @endif
                    </p>
                    <p class="mb-0 text-muted small">
                        Próximo vencimento: <strong>{{ $item->next_due_at->format('d/m/Y') }}</strong>
                        @if($item->last_occurrence_at)
                            · Último recebimento: {{ $item->last_occurrence_at->format('d/m/Y') }}
                        @endif
                    </p>
                </div>
                <div class="d-flex gap-2 flex-shrink-0">
                    <a href="{{ route('recurring-income.index') }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-pencil"></i> Gerenciar
                    </a>
                    <form method="POST" action="{{ route('transactions.unlink-recurring', $transaction) }}"
                          onsubmit="return confirm('Desvincular este lançamento da receita recorrente?')">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-x-lg"></i> Desvincular
                        </button>
                    </form>
                </div>
            </div>
        @else
            <p class="text-muted small mb-3">
                Este lançamento ainda não está vinculado a uma receita recorrente.
                Ao vincular, o sistema saberá que você espera receber esse valor todo mês e gerará um alerta se não chegar.
            </p>
            <form method="POST" action="{{ route('transactions.create-recurring', $transaction) }}">
                @csrf
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Nome da receita recorrente</label>
                        <input type="text" name="title" class="form-control form-control-sm @error('title') is-invalid @enderror"
                               value="{{ old('title', $transaction->description) }}" required>
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Valor esperado (R$)</label>
                        <input type="number" name="amount" step="0.01" min="0.01"
                               class="form-control form-control-sm @error('amount') is-invalid @enderror"
                               value="{{ old('amount', $transaction->amount) }}" required>
                        @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Dia do mês (1–28)</label>
                        <input type="number" name="day_of_month" min="1" max="28"
                               class="form-control form-control-sm"
                               value="{{ old('day_of_month', min($transaction->transaction_date->day, 28)) }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Próximo vencimento</label>
                        <input type="date" name="next_due_at"
                               class="form-control form-control-sm @error('next_due_at') is-invalid @enderror"
                               value="{{ old('next_due_at', $transaction->transaction_date->addMonthNoOverflow()->toDateString()) }}"
                               required>
                        @error('next_due_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-1">
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="alert_enabled" value="1"
                                   id="ri-alert-enabled" @checked(old('alert_enabled', true))>
                            <label class="form-check-label small" for="ri-alert-enabled">Alerta</label>
                        </div>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-sm btn-success w-100">
                            <i class="bi bi-arrow-repeat"></i> Vincular
                        </button>
                    </div>
                </div>
                @if($transaction->company)
                    <small class="text-muted mt-1 d-block">
                        <i class="bi bi-info-circle"></i>
                        Empresa <strong>{{ $transaction->company->name }}</strong> e categoria serão copiadas automaticamente para a receita recorrente.
                    </small>
                @endif
            </form>
        @endif

    </div>
</div>
@endif
