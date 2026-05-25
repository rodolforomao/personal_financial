@extends('layouts.adminlte')

@section('title', 'Telegram e WhatsApp')
@section('page_title', 'Notificações — Telegram e WhatsApp')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('alerts.index') }}">Alertas</a></li>
    <li class="breadcrumb-item active">Integrações</li>
@endsection

@section('content')
<div class="card card-outline card-info mb-3">
    <div class="card-header"><h3 class="card-title mb-0"><i class="bi bi-terminal"></i> Processos no servidor</h3></div>
    <div class="card-body">
        {!! $operationsGuideHtml !!}
        <details class="mt-2">
            <summary class="small text-muted" style="cursor:pointer">Lista completa de comandos artisan</summary>
            <pre class="bg-light p-2 rounded small mt-2 mb-0" style="white-space:pre-wrap">{{ $operationsGuidePlain }}</pre>
        </details>
    </div>
</div>

<form action="{{ route('integrations.settings.update') }}" method="POST" id="integrations-form">
    @csrf
<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header"><h3 class="card-title"><i class="bi bi-telegram"></i> Telegram</h3></div>
            <div class="card-body">
                <div class="mb-4 pb-3 border-bottom">
                    <label class="form-label fw-semibold">Para onde enviar os alertas?</label>
                    <input type="text" name="telegram_destination" class="form-control @error('telegram_destination') is-invalid @enderror"
                        value="{{ old('telegram_destination', $prefs['telegram_destination_display'] ?? $prefs['telegram_chat_id'] ?? '') }}"
                        placeholder="{{ $telegramHint }} ou 123456789"
                        autocomplete="off">
                    @error('telegram_destination')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">
                        Envie <strong>/start</strong> para o bot do sistema
                        @if(config('financial.integrations.telegram.bot_username'))
                            (<strong>{{ '@'.config('financial.integrations.telegram.bot_username') }}</strong>)
                        @endif
                        ou para o seu bot
                        e clique em <strong>Testar Telegram</strong> — o sistema descobre o ID automaticamente.
                        <br>Ou cole o <strong>número do chat</strong> do @userinfobot (ex. <code>1722629689</code>).
                        <br><span class="text-warning">@usuario sozinho não funciona em conversa privada no Telegram.</span>
                        <br>Envie <strong>fotos de comprovante</strong> ao bot — o sistema pergunta se os dados estão corretos antes de salvar.
                    </div>
                </div>

                <p class="text-muted small mb-3">
                    <strong>Qual bot envia?</strong> O token abaixo só é necessário se você usar <em>seu próprio</em> bot.
                    O destino acima vale para bot do sistema ou o seu.
                </p>
                <p class="text-muted small mb-3">
                    <strong>Lançamentos por mensagem:</strong> após vincular, envie ao bot textos como
                    <em>Gasto de 16.000 aporte Multfilmes GYN</em> — o sistema cria o lançamento se ainda não existir.
                    No bot: <code>/ops</code> e <code>/comandos</code>. Dev: um terminal <code>php artisan schedule:work</code> (ver card acima). Produção: <code>telegram:webhook-sync</code>.
                </p>

                <div class="mb-3">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="telegram_mode" id="tg-system" value="system"
                            @checked(($prefs['telegram_mode'] ?? 'system') === 'system')>
                        <label class="form-check-label" for="tg-system">
                            <strong>Bot do sistema</strong> (<code>TELEGRAM_BOT_TOKEN</code> no servidor)
                        </label>
                        @if($status['telegram_system'])
                            <div class="form-text text-success"><i class="bi bi-check-circle"></i> Token do sistema configurado</div>
                        @else
                            <div class="form-text text-warning"><i class="bi bi-exclamation-triangle"></i> Admin deve preencher <code>TELEGRAM_BOT_TOKEN</code> no <code>.env</code></div>
                        @endif
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="telegram_mode" id="tg-own" value="own"
                            @checked(($prefs['telegram_mode'] ?? '') === 'own')>
                        <label class="form-check-label" for="tg-own"><strong>Meu próprio bot</strong> (token do @BotFather)</label>
                    </div>
                </div>

                <div class="mb-3" id="tg-own-fields">
                    <label class="form-label">Token do seu bot</label>
                    <input type="password" name="telegram_bot_token" class="form-control" placeholder="123456:ABC..." autocomplete="off">
                    @if($status['telegram_user_key'])
                        <small class="text-success d-block mt-1">Token salvo (criptografado). Deixe em branco para manter.</small>
                    @endif
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="notify_telegram" value="1" id="notify-tg"
                        @checked($status['notify_telegram'])>
                    <label class="form-check-label" for="notify-tg">Receber alertas por Telegram</label>
                </div>

                @if($status['telegram_ready'])
                    <span class="badge text-bg-success">Pronto para enviar</span>
                @endif
            </div>
            <div class="card-footer">
                <button type="submit" formmethod="post" formaction="{{ route('integrations.test.telegram') }}" class="btn btn-outline-primary">
                    <i class="bi bi-send"></i> Testar Telegram
                </button>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h3 class="card-title"><i class="bi bi-whatsapp"></i> WhatsApp</h3></div>
            <div class="card-body">
                <div class="mb-4 pb-3 border-bottom">
                    <label class="form-label fw-semibold">Seu WhatsApp (número)</label>
                    <input type="text" name="whatsapp_phone" class="form-control @error('whatsapp_phone') is-invalid @enderror"
                        value="{{ old('whatsapp_phone', $prefs['whatsapp_phone'] ?? '') }}"
                        placeholder="+55 11 99999-9999 ou 5511999999999">
                    @error('whatsapp_phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">
                        Aceita com ou sem máscara; o sistema normaliza para só dígitos com DDI (Brasil: 55…).
                    </div>
                </div>

                <p class="text-muted small mb-3">
                    <strong>Qual API envia?</strong> Só preencha URL/token se usar gateway próprio (Evolution, Meta, etc.).
                </p>

                <div class="mb-3">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="whatsapp_mode" id="wa-system" value="system"
                            @checked(($prefs['whatsapp_mode'] ?? 'system') === 'system')>
                        <label class="form-check-label" for="wa-system">
                            <strong>API do sistema</strong>
                        </label>
                        @if($status['whatsapp_system'])
                            @if(($evolution['provider'] ?? '') === 'evolution')
                                <div class="form-text text-success">
                                    <i class="bi bi-check-circle"></i> Evolution API configurada
                                    @if(!empty($evolution['connection']['state']))
                                        — sessão: <strong>{{ $evolution['connection']['state'] }}</strong>
                                    @endif
                                </div>
                                @if(($evolution['connection']['state'] ?? '') !== 'open')
                                    <div class="form-text text-danger mt-1">
                                        <i class="bi bi-exclamation-octagon"></i>
                                        Status atual: <strong>{{ $evolution['connection']['state'] ?? 'desconhecido' }}</strong>.
                                        Abra <a href="http://127.0.0.1:8081/manager" target="_blank" rel="noopener">Evolution Manager</a>,
                                        escaneie o QR da instância <code>{{ config('financial.integrations.evolution.instance_name') }}</code>
                                        e aguarde <strong>open</strong> antes de testar.
                                    </div>
                                @endif
                            @else
                                <div class="form-text text-success"><i class="bi bi-check-circle"></i> URL e token do sistema configurados</div>
                            @endif
                        @else
                            <div class="form-text text-warning"><i class="bi bi-exclamation-triangle"></i>
                                Admin:
                                @if(($evolution['provider'] ?? 'evolution') === 'evolution')
                                    <code>EVOLUTION_API_URL</code>, <code>EVOLUTION_API_KEY</code> e <code>EVOLUTION_INSTANCE_NAME</code> no <code>.env</code>
                                @else
                                    <code>WHATSAPP_API_URL</code> e <code>WHATSAPP_API_TOKEN</code> no <code>.env</code>
                                @endif
                            </div>
                        @endif
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="whatsapp_mode" id="wa-own" value="own"
                            @checked(($prefs['whatsapp_mode'] ?? '') === 'own')>
                        <label class="form-check-label" for="wa-own"><strong>Minha própria API</strong></label>
                    </div>
                </div>

                <div id="wa-own-fields">
                    <div class="mb-3">
                        <label class="form-label">URL da API (modo próprio)</label>
                        <input type="url" name="whatsapp_api_url" class="form-control"
                            placeholder="https://sua-api.com/send">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Token da API</label>
                        <input type="password" name="whatsapp_api_token" class="form-control" autocomplete="off">
                        @if($status['whatsapp_user_key'])
                            <small class="text-success d-block mt-1">Token salvo. Deixe em branco para manter.</small>
                        @endif
                    </div>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="notify_whatsapp" value="1" id="notify-wa"
                        @checked($status['notify_whatsapp'])>
                    <label class="form-check-label" for="notify-wa">Receber alertas por WhatsApp</label>
                </div>

                @if($status['whatsapp_ready'])
                    <span class="badge text-bg-success">Pronto para enviar</span>
                @endif
            </div>
            <div class="card-footer">
                <button type="submit" formmethod="post" formaction="{{ route('integrations.test.whatsapp') }}" class="btn btn-outline-success">
                    <i class="bi bi-send"></i> Testar WhatsApp
                </button>
            </div>
        </div>

        <div class="card card-outline card-primary">
            <div class="card-body py-3">
                <p class="mb-0 small text-muted">Salva Telegram e WhatsApp de uma vez (destino, tokens e preferências de alerta).</p>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Salvar tudo
                </button>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card card-outline card-info">
            <div class="card-header"><h3 class="card-title">Por que dois campos no Telegram?</h3></div>
            <div class="card-body small">
                <p><strong>Destino</strong> = quem recebe (você: @usuario ou chat).</p>
                <p><strong>Token do bot</strong> = qual robô envia (só se for <em>seu</em> bot no @BotFather).</p>
                <p class="mb-0">No WhatsApp só precisa do <strong>número</strong>; o servidor envia via <strong>Evolution API</strong> (instância única) ou pela sua API.</p>
                <p class="mb-0 mt-2 text-muted">Setup do admin: <code>docs/WHATSAPP_EVOLUTION.md</code> no repositório.</p>
            </div>
        </div>
    </div>
</div>
</form>

@push('scripts')
<script>
document.querySelectorAll('input[name="telegram_mode"]').forEach(r => {
    const toggle = () => {
        const own = document.getElementById('tg-own').checked;
        document.getElementById('tg-own-fields').style.display = own ? 'block' : 'none';
    };
    r.addEventListener('change', toggle);
    toggle();
});
document.querySelectorAll('input[name="whatsapp_mode"]').forEach(r => {
    const toggle = () => {
        const own = document.getElementById('wa-own').checked;
        document.getElementById('wa-own-fields').style.display = own ? 'block' : 'none';
    };
    r.addEventListener('change', toggle);
    toggle();
});
</script>
@endpush
@endsection
