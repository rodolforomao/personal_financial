@extends('layouts.adminlte')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard CFO')
@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-success">
            <div class="inner">
                <h3>R$ {{ number_format($cashFlow['current_month']->total_income, 2, ',', '.') }}</h3>
                <p>Receitas (mês)</p>
            </div>
            <div class="icon"><i class="bi bi-arrow-down-circle"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-danger">
            <div class="inner">
                <h3>R$ {{ number_format($cashFlow['current_month']->total_expense, 2, ',', '.') }}</h3>
                <p>Despesas (mês)</p>
            </div>
            <div class="icon"><i class="bi bi-arrow-up-circle"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-primary">
            <div class="inner">
                <h3>R$ {{ number_format($cashFlow['current_month']->net_cash_flow, 2, ',', '.') }}</h3>
                <p>Fluxo líquido</p>
            </div>
            <div class="icon"><i class="bi bi-graph-up"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box text-bg-warning">
            <div class="inner">
                <h3>R$ {{ number_format($patrimony, 2, ',', '.') }}</h3>
                <p>Patrimônio</p>
            </div>
            <div class="icon"><i class="bi bi-bank"></i></div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Transações recentes</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Descrição</th>
                            <th>Tipo</th>
                            <th class="text-end">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentTransactions as $tx)
                            <tr>
                                <td>{{ $tx->transaction_date->format('d/m/Y') }}</td>
                                <td>{{ $tx->description }}</td>
                                <td>
                                    <span class="badge text-bg-{{ $tx->type->value === 'income' ? 'success' : 'danger' }}">
                                        {{ $tx->type->value }}
                                    </span>
                                </td>
                                <td class="text-end">R$ {{ number_format($tx->amount, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">Nenhuma transação</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-outline card-{{ $forecast->risk_level === 'critical' ? 'danger' : 'info' }}">
            <div class="card-header"><h3 class="card-title">Previsão 90 dias</h3></div>
            <div class="card-body">
                <p><strong>Saldo projetado:</strong> R$ {{ number_format($forecast->projected_balance, 2, ',', '.') }}</p>
                <p><strong>Risco:</strong> <span class="badge text-bg-secondary">{{ $forecast->risk_level }}</span></p>
            </div>
        </div>
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h3 class="card-title mb-0">Alertas</h3>
                <a href="{{ route('alerts.index') }}" class="btn btn-sm btn-outline-primary">Ver</a>
            </div>
            <ul class="list-group list-group-flush">
                @forelse($openAlerts as $alert)
                    <li class="list-group-item">
                        <small class="text-muted">{{ $alert->severity->value }}</small><br>
                        {{ $alert->title }}
                    </li>
                @empty
                    <li class="list-group-item text-muted">Nenhum alerta aberto</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
