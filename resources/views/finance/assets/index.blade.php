@extends('layouts.adminlte')

@section('title', 'Patrimônio')
@section('page_title', 'Patrimônio')
@section('breadcrumb')
    <li class="breadcrumb-item active">Patrimônio</li>
@endsection

@section('content')
<div class="row mb-3">
    <div class="col-md-4">
        <div class="small-box text-bg-warning">
            <div class="inner">
                <h3>R$ {{ number_format($total, 2, ',', '.') }}</h3>
                <p>Total patrimônio</p>
            </div>
            <div class="icon"><i class="bi bi-piggy-bank"></i></div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title">Novo item</h3></div>
            <form action="{{ route('assets.store') }}" method="POST">
                @csrf
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipo</label>
                        <select name="type" class="form-select" required>
                            <option value="property">Imóvel</option>
                            <option value="vehicle">Veículo</option>
                            <option value="investment">Investimento</option>
                            <option value="cash">Caixa / reserva</option>
                            <option value="other">Outro</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Valor atual (R$)</label>
                        <input type="number" step="0.01" name="current_value" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Valor aquisição (R$)</label>
                        <input type="number" step="0.01" name="acquisition_value" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Data aquisição</label>
                        <input type="date" name="acquired_at" class="form-control">
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-body table-responsive p-0">
                <table id="dt-assets" class="table table-hover mb-0">
                    <thead>
                        <tr><th>Nome</th><th>Tipo</th><th class="text-end">Valor atual</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse($assets as $asset)
                            <tr>
                                <td>{{ $asset->name }}</td>
                                <td>{{ $asset->type }}</td>
                                <td class="text-end">R$ {{ number_format($asset->current_value, 2, ',', '.') }}</td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#edit-asset-{{ $asset->id }}">Editar</button>
                                    <form action="{{ route('assets.destroy', $asset) }}" method="POST" class="d-inline" onsubmit="return confirm('Remover?');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Excluir</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">Cadastre imóveis, investimentos e reservas</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
new DataTable('#dt-assets', {
    searching: true,
    pageLength: 25,
    lengthMenu: [[10, 25, 50, 100, 250, -1], ['10', '25', '50', '100', '250', 'Todos']],
    language: { url: 'https://cdn.datatables.net/plug-ins/2.0.8/i18n/pt-BR.json' },
    columnDefs: [{ targets: -1, orderable: false, searchable: false }],
});
</script>
@endpush

@foreach($assets as $asset)
<div class="modal fade" id="edit-asset-{{ $asset->id }}" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('assets.update', $asset) }}" method="POST" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header"><h5 class="modal-title">Editar {{ $asset->name }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nome</label>
                    <input type="text" name="name" class="form-control" value="{{ $asset->name }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tipo</label>
                    <select name="type" class="form-select" required>
                        @foreach(['property'=>'Imóvel','vehicle'=>'Veículo','investment'=>'Investimento','cash'=>'Caixa','other'=>'Outro'] as $v => $l)
                            <option value="{{ $v }}" @selected($asset->type === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Valor atual</label>
                    <input type="number" step="0.01" name="current_value" class="form-control" value="{{ $asset->current_value }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Valor aquisição</label>
                    <input type="number" step="0.01" name="acquisition_value" class="form-control" value="{{ $asset->acquisition_value }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Data aquisição</label>
                    <input type="date" name="acquired_at" class="form-control" value="{{ $asset->acquired_at?->format('Y-m-d') }}">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>
@endforeach
@endsection
