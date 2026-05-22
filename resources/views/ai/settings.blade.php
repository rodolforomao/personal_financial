@extends('layouts.adminlte')

@section('title', 'Configuração IA')
@section('page_title', 'Configuração de IA')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('ai.assistant') }}">Assistente</a></li>
    <li class="breadcrumb-item active">Configuração</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Provedor, modelo e API key</h3></div>
            <form action="{{ route('ai.settings.update') }}" method="POST" id="ai-settings-form">
                @csrf
                @method('PUT')
                <div class="card-body">
                    @if($status['is_platform_internal'])
                        <div class="alert alert-success py-2">
                            <i class="bi bi-shield-check"></i>
                            Você é <strong>usuário interno</strong> da plataforma — IA do sistema sem cobrança.
                        </div>
                    @endif

                    <div class="mb-4">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="mode" id="mode-system" value="system"
                                @checked(($prefs['mode'] ?? 'system') === 'system')>
                            <label class="form-check-label" for="mode-system">
                                <strong>IA da plataforma</strong>
                                @if($status['is_platform_internal'])
                                    <span class="text-success">(gratuita para você)</span>
                                @else
                                    <span class="text-muted">(uso cobrado conforme política)</span>
                                @endif
                            </label>
                            <div class="form-text">
                                @if($status['system_available'])
                                    <span class="text-success"><i class="bi bi-check-circle"></i> Disponível neste servidor</span>
                                @else
                                    <span class="text-warning"><i class="bi bi-exclamation-triangle"></i> Configure <code>OPENAI_API_KEY</code> no <code>.env</code></span>
                                @endif
                            </div>
                        </div>

                        <div class="mb-3 ps-4 border-start" id="platform-billing-fields">
                            @unless($status['is_platform_internal'])
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="accept_platform_billing" value="1" id="accept-billing"
                                        @checked($status['platform_ai_accepted']) @disabled($status['platform_ai_accepted'])>
                                    <label class="form-check-label" for="accept-billing">
                                        Solicito usar a IA da plataforma e aceito que o consumo será <strong>cobrado</strong>
                                        conforme a política de uso (modelo e volume).
                                    </label>
                                </div>
                                @if($status['platform_ai_accepted'])
                                    <small class="text-success d-block mt-1">Aceite registrado. Para revogar, mude para &quot;Minha própria API key&quot;.</small>
                                @endif
                            @endunless
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="mode" id="mode-own" value="own"
                                @checked(($prefs['mode'] ?? '') === 'own')>
                            <label class="form-check-label" for="mode-own">
                                <strong>Minha própria API key</strong>
                            </label>
                            <div class="form-text">Chave criptografada na sua conta.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Provedor</label>
                        <select name="provider" id="ai-provider" class="form-select">
                            @foreach($providers as $key => $label)
                                <option value="{{ $key }}" @selected($selectedProvider === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Modelo</label>
                        <select name="model" id="ai-model" class="form-select" required>
                            @foreach($modelsByProvider as $prov => $models)
                                <optgroup label="{{ $providers[$prov] ?? $prov }}" data-provider="{{ $prov }}">
                                    @foreach($models as $id => $meta)
                                        <option value="{{ $id }}"
                                            data-provider="{{ $prov }}"
                                            data-tier="{{ $meta['tier'] ?? '' }}"
                                            @selected($selectedProvider === $prov && $selectedModel === $id)>
                                            {{ $meta['label'] ?? $id }}
                                            @if(!empty($meta['tier']))
                                                ({{ match($meta['tier']) { 'economy' => 'econômico', 'premium' => 'premium', default => 'padrão' } }})
                                            @endif
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <small class="text-muted" id="model-hint"></small>
                    </div>

                    <div class="mb-3" id="own-key-fields">
                        <label class="form-label">API key (modo "Minha própria")</label>
                        <input type="password" name="api_key" class="form-control" placeholder="sk-..." autocomplete="off">
                        @if($status['user_has_key'])
                            <small class="text-success d-block mt-1">Chave salva. Deixe em branco para manter.</small>
                        @endif
                    </div>
                </div>
                <div class="card-footer d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                    <a href="{{ route('ai.assistant') }}" class="btn btn-secondary">Voltar ao assistente</a>
                    @if($status['user_has_key'])
                        <form action="{{ route('ai.settings.remove-key') }}" method="POST" class="ms-auto">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="provider" id="remove-key-provider" value="{{ $selectedProvider }}">
                            <button type="submit" class="btn btn-outline-danger btn-sm">Remover chave salva</button>
                        </form>
                    @endif
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card card-outline card-info">
            <div class="card-header"><h3 class="card-title">Status</h3></div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        @if($status['ready'])
                            <i class="bi bi-check-circle-fill text-success"></i> IA pronta
                            <small class="text-muted">
                                @if($status['active_source'] === 'user')
                                    (sua chave)
                                @elseif($status['active_source'] === 'system_internal')
                                    (plataforma — interno)
                                @else
                                    (plataforma — cobrável)
                                @endif
                            </small>
                        @else
                            <i class="bi bi-x-circle-fill text-danger"></i> IA não configurada
                            @if(($status['blocked_reason'] ?? '') === 'ai_platform_billing_not_accepted')
                                <br><small class="text-warning">Aceite o uso cobrado da IA da plataforma.</small>
                            @endif
                        @endif
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-cpu"></i> Modelo ativo:
                        <strong>{{ $status['model_label'] ?? '—' }}</strong>
                        <br><small class="text-muted font-monospace">{{ $status['model'] ?? '' }}</small>
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-building"></i> Sistema: {{ $status['system_available'] ? 'disponível' : 'indisponível' }}
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-person"></i> Sua chave: {{ $status['user_has_key'] ? 'cadastrada' : 'não cadastrada' }}
                    </li>
                    @if($status['is_platform_internal'])
                        <li><i class="bi bi-shield-check text-success"></i> Conta interna — sem cobrança</li>
                    @elseif($status['platform_ai_accepted'])
                        <li><i class="bi bi-credit-card"></i> Plataforma: aceite de cobrança ativo</li>
                    @endif
                </ul>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h3 class="card-title">Guia rápido</h3></div>
            <div class="card-body small">
                <p><strong>Econômico:</strong> assistente, categorização, perguntas frequentes.</p>
                <p><strong>Padrão / Premium:</strong> análise do ecossistema, insights complexos, observabilidade.</p>
                <p class="mb-0 text-muted">O modelo vale para sistema e chave própria — escolha uma vez e usa em todo o workspace.</p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const modelHints = @json($modelHints);

function syncModels() {
    const provider = document.getElementById('ai-provider').value;
    const select = document.getElementById('ai-model');
    const hintEl = document.getElementById('model-hint');
    const removeProv = document.getElementById('remove-key-provider');
    if (removeProv) removeProv.value = provider;

    let firstVisible = null;
    Array.from(select.options).forEach(opt => {
        const show = opt.dataset.provider === provider;
        opt.hidden = !show;
        opt.disabled = !show;
        if (show && !firstVisible) firstVisible = opt;
    });
    const current = select.selectedOptions[0];
    if (!current || current.disabled) {
        select.value = firstVisible?.value || '';
    }
    const hints = modelHints[provider] || {};
    hintEl.textContent = hints[select.value] || '';
}

document.getElementById('ai-provider').addEventListener('change', syncModels);
document.getElementById('ai-model').addEventListener('change', function () {
    const provider = document.getElementById('ai-provider').value;
    const hints = modelHints[provider] || {};
    document.getElementById('model-hint').textContent = hints[this.value] || '';
});
function syncModeFields() {
    const own = document.getElementById('mode-own').checked;
    document.getElementById('own-key-fields').style.display = own ? 'block' : 'none';
    const billing = document.getElementById('platform-billing-fields');
    if (billing) billing.style.display = own ? 'none' : 'block';
}
document.querySelectorAll('input[name="mode"]').forEach(el => {
    el.addEventListener('change', syncModeFields);
});
syncModels();
syncModeFields();
</script>
@endpush
