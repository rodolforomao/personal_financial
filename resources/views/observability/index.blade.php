@extends('layouts.adminlte')

@section('title', 'Diagnóstico')
@section('page_title', 'Diagnóstico — logs e alertas')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('alerts.index') }}">Alertas</a></li>
    <li class="breadcrumb-item active">Diagnóstico</li>
@endsection

@section('content')
<div class="row mb-3">
    <div class="col-md-2">
        <div class="small-box text-bg-primary">
            <div class="inner">
                <h3>{{ $summary['total_events'] }}</h3>
                <p>Eventos no período</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="small-box text-bg-warning">
            <div class="inner">
                <h3>{{ $summary['unread_alerts'] }}</h3>
                <p>Alertas não lidos</p>
            </div>
        </div>
    </div>
    @if($canViewLogs)
    <div class="col-md-2">
        <div class="small-box text-bg-danger">
            <div class="inner">
                <h3>{{ $summary['critical_logs'] }}</h3>
                <p>Erros no log</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="small-box text-bg-secondary">
            <div class="inner">
                <h3>{{ $summary['warnings_logs'] }}</h3>
                <p>Avisos no log</p>
            </div>
        </div>
    </div>
    @endif
    <div class="col-md-2">
        <div class="small-box text-bg-info">
            <div class="inner">
                <h3>{{ $summary['telegram_issues'] }}</h3>
                <p>Telegram</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="small-box text-bg-dark">
            <div class="inner">
                <h3>{{ $summary['ai_issues'] }}</h3>
                <p>IA</p>
            </div>
        </div>
    </div>
</div>

<div class="card card-outline card-warning mb-4">
    <div class="card-header"><h3 class="card-title"><i class="bi bi-lightbulb"></i> Interpretação automática</h3></div>
    <div class="card-body p-0">
        <ul class="list-group list-group-flush">
            @foreach($insights as $insight)
                <li class="list-group-item d-flex gap-3 align-items-start">
                    <span class="badge text-bg-{{ match($insight['priority']) {
                        'critical' => 'danger',
                        'high' => 'warning',
                        'medium' => 'info',
                        default => 'secondary'
                    } }}">{{ $insight['priority'] }}</span>
                    <div>
                        <strong>{{ $insight['label'] }}</strong>
                        <p class="mb-0 small text-muted">{{ $insight['detail'] }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</div>

@if($canViewLogs)
@php
    $obsFiltersActive = !empty($filters['level']) || !empty($filters['search']) || !empty($filters['file']) || (int)($filters['days'] ?? 7) !== 7;
@endphp
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center filter-card-header @if(!$obsFiltersActive) collapsed @endif"
         data-bs-toggle="collapse" data-bs-target="#obs-filter-collapse"
         aria-expanded="{{ $obsFiltersActive ? 'true' : 'false' }}" aria-controls="obs-filter-collapse">
        <h3 class="card-title mb-0">
            Filtros
            <i class="bi bi-chevron-down ms-1 filter-chevron" style="font-size:.75rem"></i>
        </h3>
        @if($obsFiltersActive)
            <span class="badge text-bg-primary">Filtros ativos</span>
        @endif
    </div>
    <div class="collapse @if($obsFiltersActive) show @endif" id="obs-filter-collapse">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small">Período (dias)</label>
                <select name="days" class="form-select form-select-sm">
                    @foreach([1, 3, 7, 14, 30] as $d)
                        <option value="{{ $d }}" @selected((int)($filters['days'] ?? 7) === $d)>{{ $d }} dias</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Nível log</label>
                <select name="level" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach(['ERROR', 'WARNING', 'INFO', 'DEBUG'] as $lvl)
                        <option value="{{ $lvl }}" @selected(($filters['level'] ?? '') === $lvl)>{{ $lvl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Arquivo</label>
                <select name="file" class="form-select form-select-sm">
                    <option value="">Mais recente</option>
                    @foreach($logFiles as $f)
                        <option value="{{ $f['name'] }}" @selected(($filters['file'] ?? '') === $f['name'])>{{ $f['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Buscar</label>
                <input type="text" name="search" class="form-control form-control-sm" value="{{ $filters['search'] ?? '' }}" placeholder="telegram, sql, openai…">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">Filtrar</button>
            </div>
        </form>
    </div>
    </div>
</div>
@else
<div class="alert alert-secondary mb-4">
    <i class="bi bi-info-circle"></i> Diagnóstico técnico disponível apenas para <strong>administradores</strong>.
    Você vê os alertas financeiros do workspace abaixo.
</div>
@endif

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Linha do tempo unificada</h3>
        <a href="{{ route('alerts.index') }}" class="btn btn-sm btn-outline-primary">Ver só alertas</a>
    </div>
    <div class="card-body p-0">
        @forelse($timeline as $event)
            <div class="border-bottom p-3 {{ $event['kind'] === 'alert' && !($event['is_read'] ?? true) ? 'bg-warning-subtle' : '' }}">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                    <span class="badge text-bg-{{ $event['kind'] === 'alert' ? 'primary' : 'secondary' }}">
                        {{ $event['kind'] === 'alert' ? 'Alerta financeiro' : 'Log' }}
                    </span>
                    <span class="badge text-bg-{{ match(strtoupper($event['level'])) {
                        'CRITICAL', 'ERROR', 'EMERGENCY' => 'danger',
                        'WARNING' => 'warning',
                        default => 'info'
                    } }}">{{ $event['level'] }}</span>
                    <span class="badge text-bg-light text-dark">{{ $event['category'] }}</span>
                    <small class="text-muted ms-auto">{{ $event['datetime']->format('d/m/Y H:i:s') }}</small>
                    @if($event['file'])
                        <small class="text-muted font-monospace">{{ $event['file'] }}</small>
                    @endif
                </div>
                <h6 class="mb-1">{{ $event['interpretation_title'] ?? $event['title'] }}</h6>
                <p class="mb-2 small">{{ Str::limit($event['message'], 500) }}</p>
                <div class="alert alert-light border py-2 px-3 mb-2 small">
                    <i class="bi bi-arrow-right-circle text-primary"></i>
                    <strong>O que fazer:</strong> {{ $event['hint'] }}
                </div>
                @if($event['kind'] === 'alert' && !($event['is_read'] ?? true))
                    <span class="badge text-bg-warning">Não lido</span>
                @endif
                @if(!empty($event['context']))
                    <details class="mt-2">
                        <summary class="small text-muted">Contexto técnico</summary>
                        <pre class="small bg-dark text-light p-2 rounded mb-0 mt-1" style="max-height:200px;overflow:auto">{{ json_encode($event['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </details>
                @endif
                @if(!empty($event['raw']) && $canViewLogs)
                    <details class="mt-2">
                        <summary class="small text-muted">Linha bruta do log</summary>
                        <pre class="small bg-body-secondary p-2 rounded mb-0 mt-1" style="max-height:160px;overflow:auto">{{ $event['raw'] }}</pre>
                    </details>
                @endif
            </div>
        @empty
            <div class="p-4 text-center text-muted">
                Nenhum evento no período. Ajuste filtros ou aguarde novos alertas/logs.
            </div>
        @endforelse
    </div>
</div>
@endsection
