@extends('layouts.adminlte')

@section('title', 'Editar operação')
@section('page_title', 'Editar: '.$operation->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('operations.index') }}">Operações</a></li>
    <li class="breadcrumb-item"><a href="{{ route('operations.show', $operation) }}">{{ $operation->name }}</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
<div class="card">
    <form action="{{ route('operations.update', $operation) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="card-body row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Nome</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $operation->name) }}" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Empresa</label>
                <select name="company_id" class="form-select">
                    <option value="">—</option>
                    @foreach($companies as $c)
                        <option value="{{ $c->id }}" @selected(old('company_id', $operation->company_id) == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 mb-3">
                <label class="form-label">Descrição</label>
                <textarea name="description" class="form-control" rows="2">{{ old('description', $operation->description) }}</textarea>
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input type="hidden" name="exclude_from_main_dashboard" value="0">
                    <input class="form-check-input" type="checkbox" name="exclude_from_main_dashboard" value="1"
                           id="exclude-from-dashboard" @checked(old('exclude_from_main_dashboard', $operation->exclude_from_main_dashboard))>
                    <label class="form-check-label" for="exclude-from-dashboard">
                        Não incluir no dashboard consolidado
                    </label>
                </div>
                <small class="text-muted">Desmarque para que os lançamentos desta operação entrem no modo consolidado do dashboard.</small>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Salvar</button>
            <a href="{{ route('operations.show', $operation) }}" class="btn btn-secondary">Voltar</a>
        </div>
    </form>
</div>
@endsection
