@extends('layouts.adminlte')

@section('title', $operation->name)
@section('page_title', $operation->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('operations.index') }}">Operações</a></li>
    <li class="breadcrumb-item active">{{ $operation->name }}</li>
@endsection

@section('content')
<div class="row mb-3">
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-success">
            <div class="inner">
                <h3>R$ {{ number_format($summary['income'], 2, ',', '.') }}</h3>
                <p>Receitas brutas (mês)</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-danger">
            <div class="inner">
                <h3>R$ {{ number_format($summary['expense'], 2, ',', '.') }}</h3>
                <p>Despesas (mês)</p>
            </div>
        </div>
    </div>
    @if($summary['has_partners'])
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-info">
            <div class="inner">
                <h3>R$ {{ number_format($summary['my_income'], 2, ',', '.') }}</h3>
                <p>Minha parte ({{ $summary['partners_count'] }} sócios)</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-primary">
            <div class="inner">
                <h3>R$ {{ number_format($summary['my_net'], 2, ',', '.') }}</h3>
                <p>Meu resultado (mês)</p>
            </div>
        </div>
    </div>
    @else
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-primary">
            <div class="inner">
                <h3>R$ {{ number_format($summary['net'], 2, ',', '.') }}</h3>
                <p>Resultado do mês</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6 d-flex align-items-center">
        <a href="{{ route('transactions.create', ['operation_id' => $operation->id]) }}" class="btn btn-primary w-100">
            <i class="bi bi-plus-lg"></i> Novo lançamento
        </a>
    </div>
    @endif
</div>

@if($summary['has_partners'])
<div class="row mb-3">
    <div class="col-lg-4 col-6 d-flex align-items-center">
        <a href="{{ route('transactions.create', ['operation_id' => $operation->id]) }}" class="btn btn-primary w-100">
            <i class="bi bi-plus-lg"></i> Novo lançamento
        </a>
    </div>
    @if($summary['total_invested'] !== null)
    <div class="col-lg-8">
        <div class="card card-outline card-info mb-0">
            <div class="card-body py-2">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <small class="text-muted d-block">Total investido (todos)</small>
                        <strong>R$ {{ number_format($summary['total_invested'], 2, ',', '.') }}</strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Total recebido acumulado (todos)</small>
                        <strong class="text-success">R$ {{ number_format($summary['total_income_alltime'], 2, ',', '.') }}</strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Meu retorno acumulado (1/{{ $summary['partners_count'] }})</small>
                        <strong class="text-info">R$ {{ number_format($summary['my_total_income_alltime'], 2, ',', '.') }}</strong>
                        @php
                            $myInvested = $summary['total_invested'] / $summary['partners_count'];
                            $returnPct = $myInvested > 0 ? ($summary['my_total_income_alltime'] / $myInvested) * 100 : null;
                        @endphp
                        @if($returnPct !== null)
                            <span class="badge text-bg-{{ $returnPct >= 100 ? 'success' : 'warning' }} ms-1">
                                {{ number_format($returnPct, 1, ',', '.') }}% do meu aporte (R$ {{ number_format($myInvested, 2, ',', '.') }})
                            </span>
                        @endif
                    </div>
                </div>
                @if($returnPct !== null)
                <div class="progress mt-2" style="height:6px" title="{{ number_format($returnPct, 1, ',', '.') }}% recuperado">
                    <div class="progress-bar bg-{{ $returnPct >= 100 ? 'success' : 'info' }}"
                         style="width: {{ min($returnPct, 100) }}%"></div>
                </div>
                @endif
            </div>
        </div>
    </div>
    @else
    <div class="col-lg-8">
        <div class="card card-outline card-secondary mb-0">
            <div class="card-body py-2">
                <div class="row">
                    <div class="col-md-6">
                        <small class="text-muted d-block">Total recebido acumulado (todos)</small>
                        <strong class="text-success">R$ {{ number_format($summary['total_income_alltime'], 2, ',', '.') }}</strong>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Meu acumulado (1/{{ $summary['partners_count'] }})</small>
                        <strong class="text-info">R$ {{ number_format($summary['my_total_income_alltime'], 2, ',', '.') }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endif

@if($operation->company)
<p class="text-muted mb-3">
    Empresa: <strong>{{ $operation->company->name }}</strong>
    · <a href="{{ route('operations.edit', $operation) }}">Editar operação</a>
</p>
@endif

<div class="row">
    <div class="col-lg-5">
        <div class="card card-outline card-secondary">
            <div class="card-header"><h3 class="card-title">Unidades</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Unidade</th>
                            <th class="text-end">Receita bruta</th>
                            <th class="text-end">Despesa</th>
                            @if($summary['has_partners'])
                            <th class="text-end text-info">Minha parte</th>
                            @endif
                            <th class="text-end">Líquido</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($summary['units'] as $row)
                            @php $unit = $row['unit']; @endphp
                            <tr>
                                <td>
                                    @if($unit)
                                        <strong>{{ $unit->displayName() }}</strong>
                                    @else
                                        <span class="text-muted">Sem unidade</span>
                                    @endif
                                </td>
                                <td class="text-end text-success">R$ {{ number_format($row['income'], 2, ',', '.') }}</td>
                                <td class="text-end text-danger">R$ {{ number_format($row['expense'], 2, ',', '.') }}</td>
                                @if($summary['has_partners'])
                                <td class="text-end text-info">R$ {{ number_format($row['my_income'], 2, ',', '.') }}</td>
                                @endif
                                <td class="text-end">
                                    <strong>R$ {{ number_format($summary['has_partners'] ? $row['my_net'] : $row['net'], 2, ',', '.') }}</strong>
                                </td>
                                <td class="text-end">
                                    @if($unit)
                                        <a href="{{ route('transactions.create', ['operation_id' => $operation->id, 'operation_unit_id' => $unit->id]) }}" class="btn btn-xs btn-outline-secondary btn-sm">Lançar</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        @if($operation->units->isEmpty())
                            <tr><td colspan="{{ $summary['has_partners'] ? 6 : 5 }}" class="text-muted text-center">Cadastre as unidades abaixo.</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <form action="{{ route('operations.units.store', $operation) }}" method="POST" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-5">
                        <label class="form-label small mb-0">Nome da unidade</label>
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="Ex.: Apto 101, Loja 03" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-0">Código (opcional)</label>
                        <input type="text" name="code" class="form-control form-control-sm" placeholder="101">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-sm btn-primary w-100">Adicionar unidade</button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h3 class="card-title mb-0">Lançamentos recentes</h3>
                <a href="{{ route('transactions.index', ['operation_id' => $operation->id]) }}" class="btn btn-sm btn-outline-secondary">Ver todos</a>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Unidade</th>
                            <th>Descrição</th>
                            <th>Tipo</th>
                            <th class="text-end">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentTransactions as $tx)
                            <tr>
                                <td>{{ $tx->transaction_date->format('d/m/Y') }}</td>
                                <td>{{ $tx->operationUnit?->displayName() ?? '—' }}</td>
                                <td>
                                    <a href="{{ route('transactions.edit', $tx) }}">{{ $tx->description }}</a>
                                    @if($tx->linkedTransaction)
                                        <a href="{{ route('transactions.edit', $tx->linkedTransaction) }}"
                                           class="ms-1 badge text-bg-secondary text-decoration-none"
                                           title="Saída espelhada no capital pessoal: #{{ $tx->linkedTransaction->id }}">
                                            <i class="bi bi-arrow-left-right"></i> capital pessoal
                                        </a>
                                    @elseif($tx->type->value === 'income')
                                        <form method="POST" action="{{ route('transactions.mirror-personal', $tx) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="ms-1 badge text-bg-warning border-0 text-decoration-none"
                                                    style="cursor:pointer"
                                                    title="Criar saída no capital pessoal (double-entry)">
                                                <i class="bi bi-plus-circle"></i> registrar saída pessoal
                                            </button>
                                        </form>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge text-bg-{{ $tx->type->value === 'income' ? 'success' : 'danger' }}">{{ $tx->type->value }}</span>
                                </td>
                                <td class="text-end">R$ {{ number_format($tx->amount, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">Nenhum lançamento nesta operação.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<ul class="list-unstyled small text-muted mt-2">
    @foreach($operation->units as $unit)
        @if(!$unit->transactions()->exists())
            <li>
                <form action="{{ route('operations.units.destroy', [$operation, $unit]) }}" method="POST" class="d-inline" onsubmit="return confirm('Remover {{ $unit->name }}?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-link btn-sm text-danger p-0">Remover {{ $unit->name }}</button>
                </form>
            </li>
        @endif
    @endforeach
</ul>
@endsection
