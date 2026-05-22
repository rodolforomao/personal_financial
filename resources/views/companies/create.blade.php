@extends('layouts.adminlte')

@section('title', 'Nova empresa')
@section('page_title', 'Nova empresa')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('companies.index') }}">Empresas</a></li>
    <li class="breadcrumb-item active">Nova</li>
@endsection

@section('content')
<div class="card">
    <form action="{{ route('companies.store') }}" method="POST" id="company-form">
        @csrf
        <div class="card-body row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Nome</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Tipo de vínculo</label>
                <select name="type" id="company-type" class="form-select" required>
                    @foreach($types as $type)
                        <option value="{{ $type->value }}" data-desc="{{ $type->description() }}">
                            {{ $type->label() }}
                        </option>
                    @endforeach
                </select>
                <small class="text-muted" id="type-hint">{{ $types[0]->description() }}</small>
            </div>
            <div class="col-md-6 mb-3" id="share-field" style="display:none">
                <label class="form-label">Participação societária (%)</label>
                <input type="number" step="0.01" name="partnership_share" class="form-control" min="0" max="100">
            </div>
            <div class="col-md-6 mb-3" id="revenue-field">
                <label class="form-label">Receita mensal esperada (R$)</label>
                <input type="number" step="0.01" name="expected_monthly_revenue" class="form-control">
                <small class="text-muted">Principalmente para empresas que te pagam</small>
            </div>
            <div class="col-12 mb-3">
                <label class="form-label">Observações</label>
                <textarea name="notes" class="form-control" rows="3"></textarea>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Salvar</button>
            <a href="{{ route('companies.index') }}" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('company-type').addEventListener('change', function () {
    const opt = this.selectedOptions[0];
    document.getElementById('type-hint').textContent = opt.dataset.desc || '';
    document.getElementById('share-field').style.display = this.value === 'partner' ? '' : 'none';
    document.getElementById('revenue-field').style.display = ['payer', 'own'].includes(this.value) ? '' : 'none';
});
document.getElementById('company-type').dispatchEvent(new Event('change'));
</script>
@endpush
