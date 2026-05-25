@extends('layouts.adminlte')

@section('title', 'Operações')
@section('page_title', 'Operações')
@section('breadcrumb')
    <li class="breadcrumb-item active">Operações</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Negócios separados do dashboard principal</h3>
        <a href="{{ route('operations.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nova operação</a>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Operação</th>
                    <th>Empresa</th>
                    <th>Apartamentos</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($operations as $op)
                    <tr>
                        <td><strong>{{ $op->name }}</strong></td>
                        <td>{{ $op->company?->name ?? '—' }}</td>
                        <td>{{ $op->units_count }}</td>
                        <td class="text-end">
                            <a href="{{ route('operations.show', $op) }}" class="btn btn-sm btn-outline-primary">Painel</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">
                            Nenhuma operação cadastrada.
                            <a href="{{ route('operations.create') }}">Criar a primeira</a> (ex.: Residencial Oliveiras).
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<p class="text-muted small mb-0">
    Lançamentos vinculados a uma operação não entram no Dashboard principal — apenas receitas/despesas da sua vida financeira geral.
</p>
@endsection
