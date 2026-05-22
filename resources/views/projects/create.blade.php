@extends('layouts.adminlte')

@section('title', 'Novo projeto')
@section('page_title', 'Novo projeto')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Projetos</a></li>
    <li class="breadcrumb-item active">Novo</li>
@endsection

@section('content')
<div class="card">
    <form action="{{ route('projects.store') }}" method="POST">
        @csrf
        <div class="card-body row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Nome</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Empresa</label>
                <select name="company_id" class="form-select">
                    <option value="">—</option>
                    @foreach($companies as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Orçamento</label>
                <input type="number" step="0.01" name="budget" class="form-control">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Retorno esperado</label>
                <input type="number" step="0.01" name="expected_return" class="form-control">
            </div>
            <div class="col-12 mb-3">
                <label class="form-label">Descrição</label>
                <textarea name="description" class="form-control" rows="3"></textarea>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Salvar</button>
            <a href="{{ route('projects.index') }}" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection
