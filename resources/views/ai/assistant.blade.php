@extends('layouts.adminlte')

@section('title', 'Assistente IA')
@section('page_title', 'Copiloto financeiro')
@section('breadcrumb')<li class="breadcrumb-item active">Assistente IA</li>@endsection

@section('content')
@if(!$aiStatus['ready'])
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i>
        A IA completa não está ativa.
        <a href="{{ route('ai.settings') }}" class="alert-link">Configure sua API key</a>
        ou use a IA do sistema.
        Perguntas simples (ex.: gastos com IA) recebem <strong>resposta local</strong> dos seus dados.
    </div>
@else
    <div class="alert alert-info py-2">
        <small>IA ativa — <strong>{{ $aiStatus['model_label'] ?? 'modelo padrão' }}</strong>
        via @if($aiStatus['active_source'] === 'user') sua API key
        @elseif($aiStatus['active_source'] === 'system_internal') plataforma (interno)
        @else plataforma (cobrável) @endif.
        <a href="{{ route('ai.settings') }}">Alterar modelo</a></small>
    </div>
@endif

<div class="row">
    <div class="col-lg-8">
        <div class="card direct-chat direct-chat-primary">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Conversa</h3>
                <a href="{{ route('ai.settings') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-gear"></i></a>
            </div>
            <div class="card-body" style="height: 420px; overflow-y: auto;">
                @forelse($messages as $msg)
                    <div class="mb-3 {{ $msg->role === 'user' ? 'text-end' : '' }}">
                        <span class="badge text-bg-{{ $msg->role === 'user' ? 'primary' : ($msg->content && str_contains($msg->content, 'não está configurada') ? 'warning' : 'success') }} mb-1">
                            {{ $msg->role === 'user' ? 'Você' : 'IA' }}
                        </span>
                        <div class="p-2 rounded {{ $msg->role === 'user' ? 'bg-primary-subtle' : 'bg-body-secondary' }}">
                            {!! nl2br(e($msg->content)) !!}
                        </div>
                    </div>
                @empty
                    <p class="text-muted">Pergunte algo como: "Quanto estou gastando com IA este mês?"</p>
                @endforelse
            </div>
            <div class="card-footer">
                <form action="{{ route('ai.ask') }}" method="POST">
                    @csrf
                    <div class="input-group">
                        <input type="text" name="question" class="form-control" placeholder="Sua pergunta financeira..." required>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Exemplos</h3></div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><i class="bi bi-chat-quote text-primary"></i> Quanto gasto com IA?</li>
                    <li class="mb-2"><i class="bi bi-chat-quote text-primary"></i> Qual meu fluxo nos próximos 90 dias?</li>
                    <li class="mb-2"><i class="bi bi-chat-quote text-primary"></i> Quais empresas atrasam pagamentos?</li>
                    <li><i class="bi bi-chat-quote text-primary"></i> Existe algum risco financeiro?</li>
                </ul>
            </div>
        </div>
        <div class="card card-primary card-outline">
            <div class="card-body">
                <a href="{{ route('ai.settings') }}" class="btn btn-primary w-100 mb-2">
                    <i class="bi bi-key"></i> Configurar API key
                </a>
                <p class="small text-muted mb-0">Use a chave do sistema (incluída no plano) ou a sua própria OpenAI/OpenRouter.</p>
            </div>
        </div>
    </div>
</div>
@endsection
