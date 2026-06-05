@extends('layouts.adminlte')

@section('title', 'Integrações')
@section('page_title', 'Integrações — Mensagens e Gmail')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('alerts.index') }}">Alertas</a></li>
    <li class="breadcrumb-item active">Integrações</li>
@endsection

@php($canViewOperationalDetails = $canViewOperationalDetails ?? false)

@section('content')
@if($canViewOperationalDetails)
    <div class="card card-outline card-warning mb-3">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h3 class="card-title mb-0"><i class="bi bi-shield-check"></i> Verificação de proxy (Tailscale + SOCKS5)</h3>
            <button type="button" id="btn-proxy-check" class="btn btn-sm btn-warning">
                <i class="bi bi-play-circle"></i> Verificar agora
            </button>
        </div>
        <div class="card-body">
            <p class="small text-muted mb-2">
                Valida cada componente da cadeia: IP do VPS → SOCKS5 (Galaxy S22 via Tailscale) → ponte HTTP local → IP de saída → configuração Evolution.
            </p>
            <div id="proxy-check-results" style="display:none">
                <div id="proxy-check-loading" class="text-center py-3" style="display:none">
                    <div class="spinner-border spinner-border-sm text-warning" role="status"></div>
                    <span class="ms-2 small">Verificando componentes…</span>
                </div>
                <ul id="proxy-check-list" class="list-group list-group-flush" style="display:none"></ul>
                <div id="proxy-check-error" class="alert alert-danger small mt-2 mb-0" style="display:none"></div>
            </div>
        </div>
    </div>

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
@endif

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
                        placeholder="+55 11 99999-9999, {{ $telegramHint }} ou 123456789"
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
                        <br>Você pode usar <strong>telefone</strong>, <strong>@usuario</strong> ou o <strong>código do chat</strong> do @userinfobot (ex. <code>1722629689</code>).
                        <br><span class="text-warning">Telefone e @usuario precisam que o bot já tenha recebido /start; telefone também depende do contato ter sido compartilhado com o bot.</span>
                        <br>Envie <strong>fotos/PDF/XML de comprovante ou nota fiscal</strong> ao bot — o sistema pergunta se os dados estão corretos antes de salvar.
                    </div>
                </div>

                <p class="text-muted small mb-3">
                    <strong>Qual bot envia?</strong> O token abaixo só é necessário se você usar <em>seu próprio</em> bot.
                    O destino acima vale para bot do sistema ou o seu.
                </p>
                <p class="text-muted small mb-3">
                    <strong>Lançamentos por mensagem:</strong> após vincular, envie ao bot textos como
                    <em>Gasto de 16.000 aporte Multfilmes GYN</em> — o sistema cria o lançamento se ainda não existir.
                    Você também pode enviar fotos, PDFs ou XMLs de comprovantes para conferência antes de salvar.
                </p>

                <div class="mb-3">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="telegram_mode" id="tg-system" value="system"
                            @checked(($prefs['telegram_mode'] ?? 'system') === 'system')>
                        <label class="form-check-label" for="tg-system">
                            <strong>Bot do sistema</strong>
                            @if($canViewOperationalDetails)
                                <span class="text-muted">(<code>TELEGRAM_BOT_TOKEN</code> no servidor)</span>
                            @endif
                        </label>
                        @if($status['telegram_system'])
                            <div class="form-text text-success"><i class="bi bi-check-circle"></i> Bot do sistema disponível</div>
                        @else
                            <div class="form-text text-warning"><i class="bi bi-exclamation-triangle"></i>
                                @if($canViewOperationalDetails)
                                    Admin deve preencher <code>TELEGRAM_BOT_TOKEN</code> no <code>.env</code>
                                @else
                                    Bot do sistema indisponível no momento. Use seu próprio bot ou fale com o suporte.
                                @endif
                            </div>
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
                                    <i class="bi bi-check-circle"></i> WhatsApp do sistema disponível
                                    @if($canViewOperationalDetails && !empty($evolution['connection']['state']))
                                        — sessão: <strong>{{ $evolution['connection']['state'] }}</strong>
                                    @endif
                                </div>
                                @if($canViewOperationalDetails && ($evolution['connection']['state'] ?? '') !== 'open')
                                    <div class="form-text text-danger mt-1">
                                        <i class="bi bi-exclamation-octagon"></i>
                                        Status atual: <strong>{{ $evolution['connection']['state'] ?? 'desconhecido' }}</strong>.
                                        Abra <a href="http://127.0.0.1:8085/manager" target="_blank" rel="noopener">Evolution Manager</a>,
                                        escaneie o QR da instância <code>{{ config('financial.integrations.evolution.instance_name') }}</code>
                                        e aguarde <strong>open</strong> antes de testar.
                                    </div>
                                @endif
                            @else
                                <div class="form-text text-success"><i class="bi bi-check-circle"></i> WhatsApp do sistema disponível</div>
                            @endif
                        @else
                            <div class="form-text text-warning"><i class="bi bi-exclamation-triangle"></i>
                                @if($canViewOperationalDetails)
                                    Admin:
                                    @if(($evolution['provider'] ?? 'evolution') === 'evolution')
                                        <code>EVOLUTION_API_URL</code>, <code>EVOLUTION_API_KEY</code> e <code>EVOLUTION_INSTANCE_NAME</code> no <code>.env</code>
                                    @else
                                        <code>WHATSAPP_API_URL</code> e <code>WHATSAPP_API_TOKEN</code> no <code>.env</code>
                                    @endif
                                @else
                                    WhatsApp do sistema indisponível no momento. Use sua própria API ou fale com o suporte.
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

        <div class="card mb-4">
            <div class="card-header"><h3 class="card-title"><i class="bi bi-robot"></i> IA para leitura de mensagens</h3></div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Quando habilitado, o sistema usa IA (Vision API e Whisper) para <strong>ler comprovantes em foto</strong>
                    e <strong>transcrever áudios</strong> recebidos pelo Telegram e WhatsApp.
                    Isso <strong>consome tokens</strong> — ative somente se quiser usar essas funcionalidades.
                </p>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="inbound_ai_enabled" value="1" id="inbound-ai"
                        @checked($status['inbound_ai_enabled'])>
                    <label class="form-check-label" for="inbound-ai">
                        <strong>Habilitar IA para ler comprovantes (foto/áudio) via Telegram e WhatsApp</strong>
                    </label>
                </div>
                @if(!$status['inbound_ai_enabled'])
                    <small class="text-muted d-block mt-2">
                        <i class="bi bi-info-circle"></i>
                        Desabilitado — comprovantes só serão lidos via OCR local (Tesseract) e áudios serão ignorados.
                    </small>
                @else
                    <small class="text-success d-block mt-2">
                        <i class="bi bi-check-circle"></i>
                        Habilitado — Vision API e Whisper ativos para mensagens recebidas.
                    </small>
                @endif
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h3 class="card-title"><i class="bi bi-envelope"></i> Gmail</h3></div>
            <div class="card-body">
                <p class="text-muted small">
                    Conecte uma conta Google para ler e-mails recentes de compras, PIX, boletos, faturas, notas fiscais
                    e recebimentos. Cada usuário conecta o próprio Gmail via OAuth; o sistema cria transações com
                    <code>source=gmail</code> e evita duplicar mensagens já lidas.
                </p>

                @if($gmail['configured'])
                    @if($gmail['connection'])
                        <div class="alert alert-success py-2">
                            <i class="bi bi-check-circle"></i>
                            Gmail conectado
                            @if($gmail['email'])
                                — <strong>{{ $gmail['email'] }}</strong>
                            @endif
                            @if($gmail['connection']->last_sync_at)
                                <br><small>Última sincronização: {{ $gmail['connection']->last_sync_at->format('d/m/Y H:i') }}</small>
                            @endif
                        </div>
                        @if($gmail['connection']->last_error)
                            <div class="alert alert-warning py-2">
                                Último erro: {{ $gmail['connection']->last_error }}
                            </div>
                        @endif
                    @else
                        <div class="alert alert-light border py-2 mb-3">
                            Gmail ainda não conectado neste workspace.
                        </div>
                    @endif
                @else
                    <div class="alert alert-warning py-2">
                        @if($canViewOperationalDetails)
                            Admin: configure a credencial OAuth do aplicativo Google
                            (<code>GMAIL_CLIENT_ID</code>, <code>GMAIL_CLIENT_SECRET</code> e <code>GMAIL_REDIRECT_URI</code>).
                            A conta Gmail e os tokens de cada usuário são salvos criptografados no banco, não no <code>.env</code>.
                        @else
                            Gmail ainda não está disponível para conexão. Fale com o suporte para liberar esse recurso.
                        @endif
                    </div>
                @endif

                <p class="small text-muted mb-0">
                    Escopo usado: leitura somente (<code>gmail.readonly</code>). Nenhum e-mail é alterado ou apagado.
                    A conexão abaixo vale apenas para o seu usuário neste workspace.
                </p>
            </div>
            <div class="card-footer d-flex flex-wrap gap-2">
                @if($gmail['configured'])
                    <a href="{{ route('integrations.gmail.connect') }}" class="btn btn-outline-primary">
                        <i class="bi bi-google"></i>
                        {{ $gmail['connection'] ? 'Reconectar Gmail' : 'Conectar Gmail' }}
                    </a>
                @endif
                @if($gmail['connection'])
                    <button type="submit" formmethod="post" formaction="{{ route('integrations.gmail.sync') }}" class="btn btn-outline-success">
                        <i class="bi bi-arrow-repeat"></i> Sincronizar agora
                    </button>
                    <button type="submit"
                            formmethod="post"
                            formaction="{{ route('integrations.gmail.disconnect') }}"
                            onclick="event.preventDefault(); const f=this.form; const m=document.createElement('input'); m.type='hidden'; m.name='_method'; m.value='DELETE'; f.appendChild(m); f.action=this.formAction; f.submit();"
                            class="btn btn-outline-danger">
                        Desconectar
                    </button>
                @endif
            </div>
        </div>

        <div class="card card-outline card-primary">
            <div class="card-body py-3">
                <p class="mb-0 small text-muted">Salva Telegram e WhatsApp de uma vez (destino, tokens e preferências de alerta). Gmail usa OAuth separado.</p>
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
                <p>No WhatsApp só precisa do <strong>número</strong>; o servidor envia via <strong>Evolution API</strong> (instância única) ou pela sua API.</p>
                <p class="mb-0">No Gmail, cada usuário autoriza a própria conta via OAuth. A credencial no Google Cloud é só do aplicativo e usa redirect <code>{{ route('integrations.gmail.callback') }}</code>.</p>
                @if($canViewOperationalDetails)
                    <p class="mb-0 mt-2 text-muted">Setup do admin: <code>docs/WHATSAPP_EVOLUTION.md</code> no repositório.</p>
                @endif
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

(function () {
    const btn = document.getElementById('btn-proxy-check');
    if (!btn) return;

    btn.addEventListener('click', function () {
        const resultsWrap = document.getElementById('proxy-check-results');
        const loading     = document.getElementById('proxy-check-loading');
        const list        = document.getElementById('proxy-check-list');
        const errBox      = document.getElementById('proxy-check-error');

        resultsWrap.style.display = 'block';
        loading.style.display     = 'block';
        list.style.display        = 'none';
        errBox.style.display      = 'none';
        list.innerHTML            = '';
        btn.disabled              = true;

        fetch('{{ route('integrations.proxy.check') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        })
        .then(r => r.json())
        .then(data => {
            loading.style.display = 'none';
            if (data.error) {
                errBox.textContent   = data.error;
                errBox.style.display = 'block';
                return;
            }
            const checks = data.checks || {};
            Object.values(checks).forEach(c => {
                const icon = c.ok
                    ? '<i class="bi bi-check-circle-fill text-success me-2"></i>'
                    : '<i class="bi bi-x-circle-fill text-danger me-2"></i>';
                const val  = c.value ? `<code class="ms-1">${c.value}</code>` : '';
                const err  = c.error && !c.ok ? `<span class="text-danger ms-2 small">(${c.error})</span>` : '';
                const li   = document.createElement('li');
                li.className   = 'list-group-item py-2 small';
                li.innerHTML   = `${icon}<strong>${c.label}</strong>${val}${err}`;
                list.appendChild(li);
            });
            list.style.display = 'block';
        })
        .catch(e => {
            loading.style.display = 'none';
            errBox.textContent   = 'Erro de comunicação: ' + e.message;
            errBox.style.display = 'block';
        })
        .finally(() => { btn.disabled = false; });
    });
})();
</script>
@endpush
@endsection
