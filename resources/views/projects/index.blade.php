@extends('layouts.adminlte')

@section('title', 'Projetos')
@section('page_title', 'Projetos')
@section('breadcrumb')<li class="breadcrumb-item active">Projetos</li>@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Projetos & ROI</h3>
        <div class="card-tools">
            <a href="{{ route('projects.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Novo</a>
        </div>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Projeto</th>
                    <th>Empresa</th>
                    <th>Progresso</th>
                    <th>Custo</th>
                    <th>Receita</th>
                    <th>ROI</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($projects as $p)
                    @php $progress = $p->progressPercent(); @endphp
                    <tr>
                        <td>
                            <a href="{{ route('projects.edit', $p) }}">{{ $p->name }}</a>
                            @if($p->milestones->isNotEmpty())
                                <br><small class="text-muted">{{ $p->milestones->where('is_completed', true)->count() }}/{{ $p->milestones->count() }} marcos</small>
                            @endif
                        </td>
                        <td>{{ $p->company?->name ?? '—' }}</td>
                        <td style="min-width:140px">
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-success" style="width: {{ min(100, $progress) }}%"></div>
                            </div>
                            <small class="text-muted">{{ number_format($progress, 0) }}%</small>
                        </td>
                        <td>R$ {{ number_format($p->total_cost, 2, ',', '.') }}</td>
                        <td>R$ {{ number_format($p->total_revenue, 2, ',', '.') }}</td>
                        <td>{{ number_format($p->roi(), 1) }}%</td>
                        <td class="text-end">
                            <a href="{{ route('projects.edit', $p) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">Nenhum projeto cadastrado</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $projects->links() }}</div>
</div>
@endsection
