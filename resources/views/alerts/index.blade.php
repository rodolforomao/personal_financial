@extends('layouts.adminlte')

@section('title', 'Alertas')
@section('page_title', 'Alertas inteligentes')
@section('breadcrumb')<li class="breadcrumb-item active">Alertas</li>@endsection

@section('content')
<div class="alert alert-info d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <span>Configure Telegram e WhatsApp para receber alertas fora do painel.</span>
    <div class="d-flex gap-2">
        <a href="{{ route('observability.index') }}" class="btn btn-sm btn-outline-dark">Diagnóstico (logs)</a>
        <a href="{{ route('integrations.settings') }}" class="btn btn-sm btn-primary">Notificações</a>
    </div>
</div>
<div class="card">
    <div class="card-body p-0">
        <ul class="list-group list-group-flush">
            @foreach($alerts as $alert)
                <li class="list-group-item d-flex justify-content-between align-items-start {{ $alert->is_read ? '' : 'list-group-item-warning' }}">
                    <div>
                        <span class="badge text-bg-{{ $alert->severity->value === 'critical' ? 'danger' : ($alert->severity->value === 'warning' ? 'warning' : 'info') }}">
                            {{ $alert->severity->value }}
                        </span>
                        <strong class="ms-2">{{ $alert->title }}</strong>
                        <p class="mb-0 text-muted small">{{ $alert->message }}</p>
                        <small>{{ $alert->triggered_at->format('d/m/Y H:i') }}</small>
                    </div>
                    @unless($alert->is_read)
                        <form action="{{ route('alerts.read', $alert) }}" method="POST">
                            @csrf @method('PATCH')
                            <button class="btn btn-sm btn-outline-success">Marcar lido</button>
                        </form>
                    @endunless
                </li>
            @endforeach
        </ul>
    </div>
    <div class="card-footer">{{ $alerts->links() }}</div>
</div>
@endsection
