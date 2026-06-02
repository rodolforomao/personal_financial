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
    Quando receber, clique em <strong>Marcar como recebida</strong> para avançar o vencimento para o próximo mês.
</p>

@php
    $today = now();
    $overdueItems = $items->filter(fn($i) => $i->is_active && $i->next_due_at->lt($today));
    $upcomingItems = $items->filter(fn($i) => $i->is_active && $i->next_due_at->gte($today));
    $inactiveItems = $items->filter(fn($i) => !$i->is_active);
@endphp

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
                <tr>
                    <td>
                        <strong>{{ $item->title }}</strong>
                        @if($item->category)
                            <span class="badge text-bg-secondary ms-1">{{ $item->category->name }}</span>
                        @endif
                    </td>
                    <td>{{ $item->company?->name ?? '—' }}</td>
                    <td class="text-end text-success fw-semibold">R$ {{ number_format($item->amount, 2, ',', '.') }}</td>
                    <td>
                        <span class="text-danger">{{ $item->next_due_at->format('d/m/Y') }}</span>
                        <small class="text-muted">({{ $item->next_due_at->diffForHumans() }})</small>
                    </td>
                    <td class="text-end">
                        <form method="POST" action="{{ route('recurring-income.mark-received', $item) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-success">
                                <i class="bi bi-check-lg"></i> Marcar como recebida
                            </button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="row">
    <div class="col-lg-5">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title">Cadastrar receita recorrente</h3></div>
            <form action="{{ route('recurring-income.store') }}" method="POST">
                @csrf
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nome / descrição</label>
                        <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                               value="{{ old('title') }}" required placeholder="Ex.: Aluguel Apto 101, Mensalidade XYZ">
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">Valor esperado (R$)</label>
                            <input type="number" name="amount" step="0.01" min="0.01"
                                   class="form-control @error('amount') is-invalid @enderror"
                                   value="{{ old('amount') }}" required placeholder="0,00">
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
                        <label class="form-label">Empresa / pagador <span class="text-muted">(opcional, mas melhora o alerta)</span></label>
                        <select name="company_id" class="form-select">
                            <option value="">— Nenhuma —</option>
                            @foreach($companies as $c)
                                <option value="{{ $c->id }}" @selected(old('company_id') == $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">O alerta verifica se chegou um lançamento desta empresa com este valor no mês.</small>
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

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Receitas cadastradas</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover table-sm mb-0">
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
                        <tr>
                            <td>
                                <strong>{{ $item->title }}</strong>
                                @if($item->category)
                                    <br><span class="badge text-bg-secondary">{{ $item->category->name }}</span>
                                @endif
                            </td>
                            <td class="small">{{ $item->company?->name ?? '—' }}</td>
                            <td class="text-end text-success fw-semibold">R$ {{ number_format($item->amount, 2, ',', '.') }}</td>
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
                                <form action="{{ route('recurring-income.update', $item) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-4">
                                            <label class="form-label small">Nome</label>
                                            <input type="text" name="title" class="form-control form-control-sm"
                                                   value="{{ $item->title }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">Valor (R$)</label>
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
                                <form action="{{ route('recurring-income.update', $item) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-4">
                                            <label class="form-label small">Nome</label>
                                            <input type="text" name="title" class="form-control form-control-sm"
                                                   value="{{ $item->title }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">Valor (R$)</label>
                                            <input type="number" name="amount" step="0.01" min="0.01"
                                                   class="form-control form-control-sm" value="{{ $item->amount }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">Próx. vencimento</label>
                                            <input type="date" name="next_due_at" class="form-control form-control-sm"
                                                   value="{{ $item->next_due_at->toDateString() }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-check mt-3">
                                                <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                                       id="active-i-{{ $item->id }}" @checked($item->is_active)>
                                                <label class="form-check-label small" for="active-i-{{ $item->id }}">Reativar</label>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="submit" class="btn btn-sm btn-primary w-100">Salvar</button>
                                        </div>
                                    </div>
                                    <input type="hidden" name="day_of_month" value="{{ $item->day_of_month }}">
                                    <input type="hidden" name="amount" value="{{ $item->amount }}">
                                    <input type="hidden" name="alert_enabled" value="{{ $item->alert_enabled ? 1 : 0 }}">
                                </form>
                                <form action="{{ route('recurring-income.destroy', $item) }}" method="POST" class="mt-2 d-inline"
                                      onsubmit="return confirm('Remover {{ $item->title }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i> Remover
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="alert alert-info small mt-3 mb-0">
            <i class="bi bi-info-circle"></i>
            <strong>Como funciona o alerta:</strong> o comando diário verifica se chegou um lançamento de receita
            com o <strong>mesmo valor</strong> e <strong>mesma empresa</strong> no mês corrente.
            Se não tiver chegado até 3 dias após o vencimento, um alerta é gerado.
            Quanto mais completo o cadastro (empresa + valor), mais precisa é a detecção.
        </div>
    </div>
</div>
@endsection
