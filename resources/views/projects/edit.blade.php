@extends('layouts.adminlte')

@section('title', 'Editar projeto')
@section('page_title', 'Editar: '.$project->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Projetos</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card">
    <form action="{{ route('projects.update', $project) }}" method="POST" id="project-form">
        @csrf
        @method('PUT')
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nome</label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                        value="{{ old('name', $project->name) }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Empresa</label>
                    <select name="company_id" class="form-select">
                        <option value="">—</option>
                        @foreach($companies as $c)
                            <option value="{{ $c->id }}" @selected(old('company_id', $project->company_id) == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        @foreach(['active' => 'Ativo', 'paused' => 'Pausado', 'completed' => 'Concluído', 'cancelled' => 'Cancelado'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $project->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Orçamento (R$)</label>
                    <input type="number" step="0.01" name="budget" class="form-control"
                        value="{{ old('budget', $project->budget) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Retorno esperado (R$)</label>
                    <input type="number" step="0.01" name="expected_return" class="form-control"
                        value="{{ old('expected_return', $project->expected_return) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Custo total (R$)</label>
                    <input type="number" step="0.01" name="total_cost" class="form-control"
                        value="{{ old('total_cost', $project->total_cost) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Receita total (R$)</label>
                    <input type="number" step="0.01" name="total_revenue" class="form-control"
                        value="{{ old('total_revenue', $project->total_revenue) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">ROI atual</label>
                    <input type="text" class="form-control" readonly
                        value="{{ number_format($project->roi(), 1, ',', '.') }}%">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Início</label>
                    <input type="date" name="starts_at" class="form-control"
                        value="{{ old('starts_at', $project->starts_at?->format('Y-m-d')) }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Previsão de término</label>
                    <input type="date" name="ends_at" class="form-control"
                        value="{{ old('ends_at', $project->ends_at?->format('Y-m-d')) }}">
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Descrição</label>
                    <textarea name="description" class="form-control" rows="2">{{ old('description', $project->description) }}</textarea>
                </div>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <div>
                    <h5 class="mb-0">Marcos / milestones</h5>
                    <small class="text-muted">Defina etapas com peso em % (soma ideal: 100%). Marcos concluídos contam no progresso.</small>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="add-milestone">
                    <i class="bi bi-plus-lg"></i> Adicionar marco
                </button>
            </div>

            <div class="mb-2">
                <span class="badge text-bg-secondary" id="milestones-weight-total">Peso total: 0%</span>
                <span class="badge text-bg-info" id="milestones-progress">Progresso: {{ number_format($project->progressPercent(), 0) }}%</span>
            </div>

            <div class="table-responsive">
                <table class="table table-sm align-middle" id="milestones-table">
                    <thead>
                        <tr>
                            <th style="width:35%">Marco</th>
                            <th style="width:12%">Peso (%)</th>
                            <th style="width:15%">Prazo</th>
                            <th style="width:10%" class="text-center">Concluído</th>
                            <th style="width:5%"></th>
                        </tr>
                    </thead>
                    <tbody id="milestones-body">
                        @php
                            $oldMilestones = old('milestones');
                            $milestones = $oldMilestones !== null
                                ? collect($oldMilestones)->values()
                                : $project->milestones;
                        @endphp
                        @forelse($milestones as $i => $m)
                            @include('projects._milestone_row', [
                                'index' => $i,
                                'milestone' => is_array($m) ? (object) $m : $m,
                            ])
                        @empty
                            @include('projects._milestone_row', ['index' => 0, 'milestone' => null])
                        @endforelse
                    </tbody>
                </table>
            </div>
            @error('milestones')<div class="text-danger small">{{ $message }}</div>@enderror
            @error('milestones.*.name')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Salvar</button>
            <a href="{{ route('projects.index') }}" class="btn btn-secondary">Voltar</a>
        </div>
    </form>
</div>

<template id="milestone-row-template">
    @include('projects._milestone_row', ['index' => '__INDEX__', 'milestone' => null])
</template>
@endsection

@push('scripts')
<script>
(function () {
    const body = document.getElementById('milestones-body');
    const template = document.getElementById('milestone-row-template');
    const weightBadge = document.getElementById('milestones-weight-total');
    const progressBadge = document.getElementById('milestones-progress');

    const reindexRows = () => {
        body.querySelectorAll('tr.milestone-row').forEach((row, index) => {
            row.querySelectorAll('[name]').forEach(el => {
                el.name = el.name.replace(/milestones\[\d+]/, `milestones[${index}]`);
            });
        });
    };

    const syncTotals = () => {
        let weight = 0;
        let progress = 0;
        body.querySelectorAll('tr.milestone-row').forEach(row => {
            const w = parseFloat(row.querySelector('[name$="[weight_percent]"]')?.value) || 0;
            const done = row.querySelector('[name$="[is_completed]"]')?.checked;
            weight += w;
            if (done) progress += w;
        });
        weightBadge.textContent = `Peso total: ${weight.toFixed(1)}%`;
        weightBadge.className = 'badge ' + (Math.abs(weight - 100) < 0.01 ? 'text-bg-success' : weight > 100 ? 'text-bg-danger' : 'text-bg-warning');
        progressBadge.textContent = `Progresso: ${progress.toFixed(0)}%`;
    };

    document.getElementById('add-milestone').addEventListener('click', () => {
        const index = body.querySelectorAll('tr.milestone-row').length;
        const html = template.innerHTML.replace(/__INDEX__/g, String(index));
        body.insertAdjacentHTML('beforeend', html);
        syncTotals();
    });

    body.addEventListener('click', (e) => {
        const btn = e.target.closest('.remove-milestone');
        if (!btn) return;
        const rows = body.querySelectorAll('tr.milestone-row');
        if (rows.length <= 1) {
            const row = btn.closest('tr');
            row.querySelectorAll('input[type=text], input[type=number], input[type=date]').forEach(el => { el.value = ''; });
            row.querySelector('input[type=checkbox]').checked = false;
            row.querySelector('input[type=hidden][name$="[id]"]')?.remove();
            syncTotals();
            return;
        }
        btn.closest('tr').remove();
        reindexRows();
        syncTotals();
    });

    body.addEventListener('input', syncTotals);
    body.addEventListener('change', syncTotals);
    syncTotals();
})();
</script>
@endpush
