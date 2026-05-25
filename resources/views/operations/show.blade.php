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
                <p>Receitas (mês)</p>
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
</div>

@if($operation->company)
<p class="text-muted mb-3">
    Empresa: <strong>{{ $operation->company->name }}</strong>
    · <a href="{{ route('operations.edit', $operation) }}">Editar operação</a>
</p>
@endif

<div class="row">
    <div class="col-lg-5">
        <div class="card card-outline card-secondary">
            <div class="card-header"><h3 class="card-title">Apartamentos / unidades</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Unidade</th><th class="text-end">Receita</th><th class="text-end">Despesa</th><th class="text-end">Líquido</th><th></th></tr></thead>
                    <tbody>
                        @foreach($summary['units'] as $row)
                            @php $unit = $row['unit']; @endphp
                            <tr>
                                <td>
                                    @if($unit)
                                        <strong>{{ $unit->displayName() }}</strong>
                                    @else
                                        <span class="text-muted">Sem apartamento</span>
                                    @endif
                                </td>
                                <td class="text-end text-success">R$ {{ number_format($row['income'], 2, ',', '.') }}</td>
                                <td class="text-end text-danger">R$ {{ number_format($row['expense'], 2, ',', '.') }}</td>
                                <td class="text-end"><strong>R$ {{ number_format($row['net'], 2, ',', '.') }}</strong></td>
                                <td class="text-end">
                                    @if($unit)
                                        <a href="{{ route('transactions.create', ['operation_id' => $operation->id, 'operation_unit_id' => $unit->id]) }}" class="btn btn-xs btn-outline-secondary btn-sm">Lançar</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        @if($operation->units->isEmpty())
                            <tr><td colspan="5" class="text-muted text-center">Cadastre os apartamentos abaixo.</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <form action="{{ route('operations.units.store', $operation) }}" method="POST" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-5">
                        <label class="form-label small mb-0">Nome do apartamento</label>
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="Apto 101" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-0">Código (opcional)</label>
                        <input type="text" name="code" class="form-control form-control-sm" placeholder="101">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-sm btn-primary w-100">Adicionar apartamento</button>
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
                            <th>Apartamento</th>
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
