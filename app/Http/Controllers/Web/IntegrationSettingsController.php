<?php

namespace App\Http\Controllers\Web;

use App\Application\Services\PlatformOperationsGuide;
use App\Core\Support\IntegrationCredentialsResolver;
use App\Core\Support\NotificationDestinationNormalizer;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Alerts\Infrastructure\Models\AlertChannel;
use Modules\Integrations\Application\Services\EvolutionService;
use Modules\Integrations\Application\Services\GmailEmailImportService;
use Modules\Integrations\Application\Services\GmailOAuthService;
use Modules\Integrations\Application\Services\TelegramService;
use Modules\Integrations\Application\Services\WhatsAppService;

class IntegrationSettingsController extends Controller
{
    public function edit(Request $request, IntegrationCredentialsResolver $resolver): View
    {
        $user = $request->user();
        $canViewOperationalDetails = $user->hasRole('admin');
        $prefs = $user->preferences['notifications'] ?? [];

        $evolution = app(EvolutionService::class);
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $gmail = app(GmailOAuthService::class);
        $gmailConnection = $gmail->connection($workspaceId, $user->id);
        $allGmailConnections = $gmail->connectionsForWorkspace($workspaceId)
            ->load('user')
            ->values();

        return view('integrations.settings', [
            'status' => $resolver->status($user->id),
            'prefs' => $prefs,
            'telegramHint' => $this->telegramPlaceholder($user->name),
            'evolution' => [
                'provider' => config('financial.integrations.whatsapp.provider', 'evolution'),
                'configured' => $evolution->configured(),
                'connection' => $evolution->configured() ? $evolution->connectionState() : null,
            ],
            'gmail' => [
                'configured' => $gmail->configured(),
                'connection' => $gmailConnection,
                'email' => $gmailConnection?->settings['email'] ?? $gmailConnection?->credentials['email'] ?? null,
                'allConnections' => $allGmailConnections,
            ],
            'canViewOperationalDetails' => $canViewOperationalDetails,
            'operationsGuideHtml' => app(PlatformOperationsGuide::class)->webCardHtml(),
            'operationsGuidePlain' => app(PlatformOperationsGuide::class)->plainTextGuide(),
        ]);
    }

    public function connectGmail(Request $request, GmailOAuthService $gmail): RedirectResponse
    {
        if (! $gmail->configured()) {
            $message = $request->user()->hasRole('admin')
                ? 'Configure GMAIL_CLIENT_ID, GMAIL_CLIENT_SECRET e GMAIL_REDIRECT_URI no .env.'
                : 'Gmail ainda não está disponível para conexão. Fale com o suporte.';

            return back()->with('warning', $message);
        }

        $state = Str::random(40);
        $request->session()->put('gmail_oauth_state', [
            'state' => $state,
            'workspace_id' => (int) $request->attributes->get('workspace_id'),
            'user_id' => $request->user()->id,
        ]);

        return redirect()->away($gmail->authorizationUrl($state));
    }

    public function gmailCallback(Request $request, GmailOAuthService $gmail): RedirectResponse
    {
        $state = $request->session()->pull('gmail_oauth_state');
        if (! is_array($state) || ! hash_equals((string) ($state['state'] ?? ''), (string) $request->query('state'))) {
            return redirect()->route('integrations.settings')->with('error', 'Retorno do Gmail inválido. Tente conectar novamente.');
        }

        if (! $request->filled('code')) {
            return redirect()->route('integrations.settings')->with('error', 'Gmail não retornou autorização.');
        }

        try {
            $result = $gmail->exchangeCode((string) $request->query('code'));
            $gmail->storeConnection(
                (int) $state['workspace_id'],
                (int) $state['user_id'],
                $result['token'],
                $result['email'],
            );
        } catch (\Throwable $e) {
            return redirect()->route('integrations.settings')->with('error', 'Falha ao conectar Gmail: '.$e->getMessage());
        }

        return redirect()->route('integrations.settings')->with('success', 'Gmail conectado. Você já pode sincronizar e-mails financeiros.');
    }

    public function disconnectGmail(Request $request, GmailOAuthService $gmail): RedirectResponse
    {
        $gmail->disconnect((int) $request->attributes->get('workspace_id'), $request->user()->id);

        return back()->with('success', 'Seu Gmail foi desconectado deste workspace.');
    }

    public function syncGmail(Request $request, GmailEmailImportService $import): RedirectResponse
    {
        $result = $import->syncUser(
            (int) $request->attributes->get('workspace_id'),
            $request->user()->id,
            (int) config('financial.integrations.gmail.sync_limit', 25),
        );

        if (! empty($result['error'])) {
            return back()->with('error', 'Falha ao sincronizar Gmail: '.$result['error']);
        }

        return back()->with(
            'success',
            "Gmail sincronizado: {$result['created']} transação(ões) criada(s), ".
            "{$result['skipped']} ignorada(s), {$result['scanned']} e-mail(s) lido(s)."
        );
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'telegram_mode' => 'required|in:system,own',
            'telegram_destination' => 'nullable|string|max:120',
            'telegram_bot_token' => 'nullable|string|max:500',
            'whatsapp_mode' => 'required|in:system,own',
            'whatsapp_phone' => 'nullable|string|max:30',
            'whatsapp_api_url' => 'nullable|url|max:500',
            'whatsapp_api_token' => 'nullable|string|max:500',
            'notify_telegram' => 'sometimes|boolean',
            'notify_whatsapp' => 'sometimes|boolean',
            'inbound_ai_enabled' => 'sometimes|boolean',
        ]);

        $user = $request->user();
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $prefs = $user->preferences ?? [];
        $n = $prefs['notifications'] ?? [];

        $n['telegram_mode'] = $validated['telegram_mode'];
        $telegramRaw = $validated['telegram_destination'] ?? null;
        $telegramNormalized = NotificationDestinationNormalizer::telegram($telegramRaw);
        if ($telegramRaw && ! $telegramNormalized) {
            return back()
                ->withInput()
                ->withErrors(['telegram_destination' => 'Use telefone (+55...), @seu_usuario, link t.me/seu_usuario ou o código do chat (ex.: 123456789).']);
        }

        $existingEnc = ($prefs['notifications'] ?? [])['telegram_bot_token_enc'] ?? null;
        $botToken = $validated['telegram_mode'] === 'system'
            ? config('financial.integrations.telegram.bot_token')
            : (! empty($validated['telegram_bot_token'])
                ? trim($validated['telegram_bot_token'])
                : $this->decryptPref($existingEnc));

        if ($telegramNormalized) {
            $n['telegram_chat_id'] = NotificationDestinationNormalizer::resolveTelegramChatId(
                $telegramNormalized,
                $botToken
            );
            $n['telegram_destination_display'] = NotificationDestinationNormalizer::telegramDisplay($telegramNormalized);
        } else {
            $n['telegram_chat_id'] = null;
            $n['telegram_destination_display'] = null;
        }

        $n['whatsapp_mode'] = $validated['whatsapp_mode'];
        $whatsappNormalized = NotificationDestinationNormalizer::whatsapp($validated['whatsapp_phone'] ?? null);
        if (! empty($validated['whatsapp_phone']) && ! $whatsappNormalized) {
            return back()
                ->withInput()
                ->withErrors(['whatsapp_phone' => 'Informe um número válido com DDI (ex.: +55 11 99999-9999 ou 5511999999999).']);
        }
        $n['whatsapp_phone'] = $whatsappNormalized;
        $n['notify_telegram'] = $request->boolean('notify_telegram');
        $n['notify_whatsapp'] = $request->boolean('notify_whatsapp');
        $n['inbound_ai_enabled'] = $request->boolean('inbound_ai_enabled');

        if ($validated['telegram_mode'] === 'own' && ! empty($validated['telegram_bot_token'])) {
            $n['telegram_bot_token_enc'] = Crypt::encryptString(trim($validated['telegram_bot_token']));
        }

        if ($validated['whatsapp_mode'] === 'own') {
            if (! empty($validated['whatsapp_api_url'])) {
                $n['whatsapp_api_url_enc'] = Crypt::encryptString(trim($validated['whatsapp_api_url']));
            }
            if (! empty($validated['whatsapp_api_token'])) {
                $n['whatsapp_api_token_enc'] = Crypt::encryptString(trim($validated['whatsapp_api_token']));
            }
        }

        $prefs['notifications'] = $n;
        $user->forceFill(['preferences' => $prefs])->save();

        $this->syncAlertChannels($user->id, $workspaceId, $n);

        return redirect()->route('integrations.settings')
            ->with('success', 'Notificações Telegram/WhatsApp salvas.');
    }

    public function testTelegram(Request $request, IntegrationCredentialsResolver $resolver): RedirectResponse
    {
        if ($request->filled('telegram_destination')) {
            $this->mergeTelegramFromRequest($request);
        }

        $config = $resolver->telegram($request->user()->id, (int) $request->attributes->get('workspace_id'));

        if (! $config) {
            return back()->with('warning', 'Informe para onde enviar no Telegram (telefone, @seu_usuario ou código numérico) e o token do bot.');
        }

        try {
            $result = app(TelegramService::class)->sendWithConfig($config,
                '✅ Teste Financial IQ — Telegram configurado com sucesso!');
        } catch (\Throwable $e) {
            report($e);

            $message = $request->user()->hasRole('admin')
                ? 'Falha inesperada ao testar Telegram: '.$e->getMessage()
                : 'Falha inesperada ao testar Telegram. Tente novamente em instantes.';

            return back()->with('error', $message);
        }

        if (! empty($result['chat_id'])) {
            $this->persistTelegramChatId($request->user(), $result['chat_id']);
        }

        return back()->with(
            ($result['ok'] ?? false) ? 'success' : 'error',
            ($result['ok'] ?? false)
                ? 'Mensagem de teste enviada no Telegram.'
                : $this->telegramTestErrorMessage($request, $result['error'] ?? null)
        );
    }

    public function testWhatsApp(Request $request, IntegrationCredentialsResolver $resolver): RedirectResponse
    {
        if ($request->filled('whatsapp_phone')) {
            $this->mergeWhatsAppFromRequest($request);
        }

        $config = $resolver->whatsapp($request->user()->id, (int) $request->attributes->get('workspace_id'));

        if (! $config) {
            return back()->with('warning', 'Informe seu telefone e confira se a integração escolhida está disponível.');
        }

        $whatsapp = app(WhatsAppService::class);
        $ok = $whatsapp->sendWithConfig($config,
            '✅ Teste Financial IQ — WhatsApp configurado com sucesso!');

        $errorHint = $whatsapp->lastFailureReason()
            ?? (($config['provider'] ?? '') === 'evolution'
                ? 'Falha ao enviar. Verifique Evolution API (instância conectada, EVOLUTION_* no .env) e o número com DDI.'
                : 'Falha ao enviar. Verifique URL, token e número.');

        if (! $request->user()->hasRole('admin') && ($config['provider'] ?? '') === 'evolution') {
            $errorHint = 'Falha ao enviar pelo WhatsApp do sistema. Tente novamente ou fale com o suporte.';
        }

        return back()->with($ok ? 'success' : 'error', $ok
            ? 'Mensagem de teste enviada no WhatsApp.'
            : $errorHint);
    }

    protected function telegramTestErrorMessage(Request $request, ?string $error): string
    {
        if ($request->user()->hasRole('admin')) {
            return $error ?? 'Falha ao enviar. Verifique token e destino.';
        }

        return 'Falha ao enviar. Confira o destino informado ou tente novamente em instantes.';
    }

    protected function persistTelegramChatId($user, string $chatId): void
    {
        $prefs = $user->preferences ?? [];
        $n = $prefs['notifications'] ?? [];
        $n['telegram_chat_id'] = $chatId;
        $prefs['notifications'] = $n;
        $user->forceFill(['preferences' => $prefs])->save();
    }

    protected function mergeTelegramFromRequest(Request $request): void
    {
        $user = $request->user();
        $normalized = NotificationDestinationNormalizer::telegram($request->input('telegram_destination'));
        if (! $normalized) {
            return;
        }

        $prefs = $user->preferences ?? [];
        $n = $prefs['notifications'] ?? [];
        $mode = $request->input('telegram_mode', $n['telegram_mode'] ?? 'system');
        $token = $mode === 'own'
            ? ($request->filled('telegram_bot_token')
                ? trim($request->input('telegram_bot_token'))
                : $this->decryptPref($n['telegram_bot_token_enc'] ?? null))
            : config('financial.integrations.telegram.bot_token');

        $n['telegram_mode'] = $mode;
        $n['telegram_chat_id'] = NotificationDestinationNormalizer::resolveTelegramChatId($normalized, $token);
        $n['telegram_destination_display'] = NotificationDestinationNormalizer::telegramDisplay($normalized);
        $prefs['notifications'] = $n;
        $user->forceFill(['preferences' => $prefs])->save();
    }

    protected function mergeWhatsAppFromRequest(Request $request): void
    {
        $normalized = NotificationDestinationNormalizer::whatsapp($request->input('whatsapp_phone'));
        if (! $normalized) {
            return;
        }

        $user = $request->user();
        $prefs = $user->preferences ?? [];
        $n = $prefs['notifications'] ?? [];
        $n['whatsapp_phone'] = $normalized;
        $n['whatsapp_mode'] = $request->input('whatsapp_mode', $n['whatsapp_mode'] ?? 'system');
        $prefs['notifications'] = $n;
        $user->forceFill(['preferences' => $prefs])->save();
    }

    protected function telegramPlaceholder(string $name): string
    {
        $slug = Str::slug($name, '');
        if (strlen($slug) >= 4 && strlen($slug) <= 32) {
            return '@'.$slug;
        }

        return '@seu_usuario';
    }

    protected function decryptPref(?string $encrypted): ?string
    {
        if (! $encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function syncAlertChannels(int $userId, int $workspaceId, array $n): void
    {
        foreach (['telegram', 'whatsapp'] as $channel) {
            $destination = $channel === 'telegram'
                ? ($n['telegram_chat_id'] ?? null)
                : ($n['whatsapp_phone'] ?? null);

            $active = $channel === 'telegram'
                ? ($n['notify_telegram'] ?? false) && $destination
                : ($n['notify_whatsapp'] ?? false) && $destination;

            if ($destination) {
                AlertChannel::query()->updateOrCreate(
                    [
                        'workspace_id' => $workspaceId,
                        'user_id' => $userId,
                        'channel' => $channel,
                    ],
                    [
                        'destination' => $destination,
                        'is_active' => $active,
                    ]
                );
            } else {
                AlertChannel::query()
                    ->where('workspace_id', $workspaceId)
                    ->where('user_id', $userId)
                    ->where('channel', $channel)
                    ->update(['is_active' => false]);
            }
        }
    }

    public function proxyCheck(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('admin')) {
            return response()->json(['error' => 'Acesso negado.'], 403);
        }

        $socksHost = config('financial.integrations.evolution.proxy_socks_host', '100.73.222.119');
        $socksPort = (int) config('financial.integrations.evolution.proxy_socks_port', 1080);
        $bridgeHost = '127.0.0.1';
        $bridgePort = (int) config('financial.integrations.evolution.http_bridge_port', 18080);

        $results = [];

        // 1. VPS direct IP
        try {
            $vpsIp = Http::timeout(8)->get('https://api.ipify.org')->body();
            $vpsIp = trim($vpsIp);
            $results['vps_ip'] = ['ok' => (bool) filter_var($vpsIp, FILTER_VALIDATE_IP), 'value' => $vpsIp, 'label' => 'IP direto do VPS'];
        } catch (\Throwable $e) {
            $results['vps_ip'] = ['ok' => false, 'value' => null, 'label' => 'IP direto do VPS', 'error' => $e->getMessage()];
        }

        // 2. SOCKS5 port (Galaxy S22 via Tailscale)
        $sock = @fsockopen($socksHost, $socksPort, $errno, $errstr, 5);
        if ($sock) {
            fclose($sock);
            $results['socks5'] = ['ok' => true, 'value' => "{$socksHost}:{$socksPort}", 'label' => 'SOCKS5 (Galaxy S22 / Tailscale)'];
        } else {
            $results['socks5'] = ['ok' => false, 'value' => "{$socksHost}:{$socksPort}", 'label' => 'SOCKS5 (Galaxy S22 / Tailscale)', 'error' => "{$errno}: {$errstr}"];
        }

        // 3. HTTP bridge (local SOCKS→HTTP proxy)
        $bridge = @fsockopen($bridgeHost, $bridgePort, $errno, $errstr, 3);
        if ($bridge) {
            fclose($bridge);
            $results['http_bridge'] = ['ok' => true, 'value' => "{$bridgeHost}:{$bridgePort}", 'label' => 'Ponte HTTP→SOCKS5 (local)'];
        } else {
            $results['http_bridge'] = ['ok' => false, 'value' => "{$bridgeHost}:{$bridgePort}", 'label' => 'Ponte HTTP→SOCKS5 (local)', 'error' => "{$errno}: {$errstr}"];
        }

        // 4. Exit IP through proxy
        $proxyExitIp = null;
        try {
            $proxyExitIp = trim(Http::withOptions(['proxy' => "http://{$bridgeHost}:{$bridgePort}"])->timeout(12)->get('https://api.ipify.org')->body());
            $results['proxy_exit_ip'] = ['ok' => (bool) filter_var($proxyExitIp, FILTER_VALIDATE_IP), 'value' => $proxyExitIp, 'label' => 'IP de saída via proxy'];
        } catch (\Throwable $e) {
            $results['proxy_exit_ip'] = ['ok' => false, 'value' => null, 'label' => 'IP de saída via proxy', 'error' => $e->getMessage()];
        }

        // 5. IP difference check
        $vpsIpVal = $results['vps_ip']['value'] ?? null;
        if ($vpsIpVal && $proxyExitIp && $vpsIpVal !== $proxyExitIp) {
            $results['ip_diff'] = ['ok' => true, 'value' => "{$vpsIpVal} ≠ {$proxyExitIp}", 'label' => 'IPs distintos (VPS ≠ proxy)'];
        } elseif ($vpsIpVal && $proxyExitIp) {
            $results['ip_diff'] = ['ok' => false, 'value' => "{$vpsIpVal} = {$proxyExitIp}", 'label' => 'IPs distintos (VPS ≠ proxy)', 'error' => 'O tráfego não está saindo pelo proxy — mesmo IP'];
        } else {
            $results['ip_diff'] = ['ok' => false, 'value' => null, 'label' => 'IPs distintos (VPS ≠ proxy)', 'error' => 'Não foi possível obter ambos os IPs'];
        }

        // 6. Evolution proxy config
        $evolutionService = app(EvolutionService::class);
        $apiUrl = config('financial.integrations.evolution.api_url');
        $apiKey = config('financial.integrations.evolution.api_key');
        $instance = config('financial.integrations.evolution.instance_name');
        try {
            $resp = Http::withHeaders(['apikey' => $apiKey])->timeout(8)->get("{$apiUrl}/proxy/find/{$instance}");
            $proxyConfig = $resp->json();
            $proxyEnabled = $proxyConfig['proxy']['enabled'] ?? $proxyConfig['enabled'] ?? false;
            $proxyHost = $proxyConfig['proxy']['host'] ?? $proxyConfig['host'] ?? '?';
            $proxyPort = $proxyConfig['proxy']['port'] ?? $proxyConfig['port'] ?? '?';
            $results['evolution_proxy'] = [
                'ok' => (bool) $proxyEnabled,
                'value' => "enabled={$proxyEnabled}, {$proxyHost}:{$proxyPort}",
                'label' => 'Proxy configurado na instância Evolution',
                'error' => $proxyEnabled ? null : 'Proxy desativado na instância — o Evolution não usará o túnel',
            ];
        } catch (\Throwable $e) {
            $results['evolution_proxy'] = ['ok' => false, 'value' => null, 'label' => 'Proxy configurado na instância Evolution', 'error' => $e->getMessage()];
        }

        // 7. Evolution instance connection state
        try {
            $conn = $evolutionService->configured() ? $evolutionService->connectionState() : null;
            $state = $conn['state'] ?? null;
            $results['evolution_state'] = [
                'ok' => $state === 'open',
                'value' => $state ?? 'desconhecido',
                'label' => 'Estado da instância Evolution',
                'error' => $state !== 'open' ? "Estado: {$state} (esperado: open)" : null,
            ];
        } catch (\Throwable $e) {
            $results['evolution_state'] = ['ok' => false, 'value' => null, 'label' => 'Estado da instância Evolution', 'error' => $e->getMessage()];
        }

        return response()->json(['checks' => $results]);
    }
}
