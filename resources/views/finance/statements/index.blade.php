@extends('layouts.adminlte')

@section('title', 'Importar extrato')
@section('page_title', 'Importar extrato OFX / CSV')
@section('breadcrumb')
    <li class="breadcrumb-item active">Extrato</li>
@endsection

@section('content')
<div class="row">
    <div class="col-md-5">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title">Novo extrato</h3></div>
            <form action="{{ route('statements.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Formato</label>
                        <select name="format" class="form-select" required>
                            <option value="ofx" @selected(old('format') === 'ofx')>OFX (banco)</option>
                            <option value="csv" @selected(old('format') === 'csv')>CSV</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Banco (OFX)</label>
                        <select name="bank" class="form-select">
                            <option value="">Detectar automaticamente</option>
                            <option value="inter" @selected(old('bank') === 'inter')>Banco Inter</option>
                            <option value="nubank" @selected(old('bank') === 'nubank')>Nubank</option>
                            <option value="itau" @selected(old('bank') === 'itau')>Itaú</option>
                            <option value="bradesco" @selected(old('bank') === 'bradesco')>Bradesco</option>
                        </select>
                        <small class="text-muted">Usado para preencher fonte (ex.: Banco Inter) e meio de pagamento nas transações importadas.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Arquivo</label>
                        <input type="file" name="file" class="form-control" accept=".ofx,.csv,.txt" required>
                        <small class="text-muted">OFX: exportação do internet banking. CSV: mapeamento de colunas na próxima etapa.</small>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">Importar e conciliar</button>
                </div>
            </form>
        </div>
        <div class="alert alert-info small">
            <strong>Fluxo:</strong> o extrato vira linhas pendentes → o sistema sugere correspondência com lançamentos existentes → você confirma, importa novos ou ignora.
        </div>
    </div>
    <div class="col-md-7">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Importações recentes</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Arquivo</th>
                            <th>Linhas</th>
                            <th>Conciliadas</th>
                            <th>Importadas</th>
                            <th>Data</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($imports as $imp)
                            <tr>
                                <td>{{ $imp->original_name }} <span class="badge text-bg-secondary">{{ strtoupper($imp->format) }}</span></td>
                                <td>{{ $imp->lines_total }}</td>
                                <td>{{ $imp->matched_count }}</td>
                                <td>{{ $imp->imported_count }}</td>
                                <td>{{ $imp->created_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    <a href="{{ route('statements.reconcile', $imp) }}" class="btn btn-sm btn-outline-primary">Conciliar</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">Nenhuma importação ainda</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($imports->hasPages())
                <div class="card-footer">{{ $imports->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
