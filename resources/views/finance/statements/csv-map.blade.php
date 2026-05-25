@extends('layouts.adminlte')

@section('title', 'Mapear CSV')
@section('page_title', 'Mapear colunas do CSV')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('statements.index') }}">Extrato</a></li>
    <li class="breadcrumb-item active">CSV</li>
@endsection

@section('content')
<div class="card card-primary col-lg-8">
    <div class="card-header"><h3 class="card-title">{{ $filename }}</h3></div>
    <form action="{{ route('statements.import.csv-submit') }}" method="POST">
        @csrf
        <div class="card-body">
            <p class="text-muted">Colunas detectadas: {{ implode(', ', $headers) }}</p>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Coluna do valor *</label>
                    <select name="amount" class="form-select" required>
                        @foreach($headers as $h)
                            <option value="{{ $h }}" @selected(stripos($h, 'valor') !== false || stripos($h, 'amount') !== false)>{{ $h }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Coluna da data *</label>
                    <select name="date" class="form-select" required>
                        @foreach($headers as $h)
                            <option value="{{ $h }}" @selected(stripos($h, 'data') !== false || stripos($h, 'date') !== false)>{{ $h }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Descrição</label>
                    <select name="description" class="form-select">
                        <option value="">—</option>
                        @foreach($headers as $h)
                            <option value="{{ $h }}" @selected(stripos($h, 'desc') !== false || stripos($h, 'hist') !== false)>{{ $h }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Contraparte</label>
                    <select name="counterparty" class="form-select">
                        <option value="">—</option>
                        @foreach($headers as $h)
                            <option value="{{ $h }}">{{ $h }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Importar e conciliar</button>
            <a href="{{ route('statements.index') }}" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection
