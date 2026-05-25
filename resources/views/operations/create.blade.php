@extends('layouts.adminlte')

@section('title', 'Nova operação')
@section('page_title', 'Nova operação')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('operations.index') }}">Operações</a></li>
    <li class="breadcrumb-item active">Nova</li>
@endsection

@section('content')
<div class="card">
    <form action="{{ route('operations.store') }}" method="POST">
        @csrf
        <div class="card-body row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Nome da operação</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', 'Residencial Oliveiras') }}" required placeholder="Ex.: Residencial Oliveiras">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Empresa vinculada</label>
                <select name="company_id" class="form-select">
                    <option value="">— Nenhuma —</option>
                    @foreach($companies as $c)
                        <option value="{{ $c->id }}" @selected(old('company_id', $preselectedCompanyId) == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
                <small class="text-muted">Se já cadastrou a empresa (ex. Residencial Oliveiras), selecione aqui.</small>
            </div>
            <div class="col-12 mb-3">
                <label class="form-label">Descrição</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Airbnb, aluguel por temporada...">{{ old('description') }}</textarea>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Criar e cadastrar apartamentos</button>
            <a href="{{ route('operations.index') }}" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection
