@extends('layouts.adminlte')

@section('title', 'Projetos')
@section('page_title', 'Projetos')
@section('breadcrumb')<li class="breadcrumb-item active">Projetos</li>@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Projetos & ROI</h3>
        <div class="card-tools">
            <a href="{{ route('projects.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Novo</a>
        </div>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Projeto</th><th>Empresa</th><th>Custo</th><th>Receita</th><th>ROI</th></tr></thead>
            <tbody>
                @foreach($projects as $p)
                    <tr>
                        <td>{{ $p->name }}</td>
                        <td>{{ $p->company?->name ?? '—' }}</td>
                        <td>R$ {{ number_format($p->total_cost, 2, ',', '.') }}</td>
                        <td>R$ {{ number_format($p->total_revenue, 2, ',', '.') }}</td>
                        <td>{{ number_format($p->roi(), 1) }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $projects->links() }}</div>
</div>
@endsection
