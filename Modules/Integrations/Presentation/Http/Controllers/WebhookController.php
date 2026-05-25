<?php

namespace Modules\Integrations\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Core\Infrastructure\Models\Workspace;
use Modules\Integrations\Application\Jobs\ProcessEvolutionInboundJob;
use Modules\Integrations\Application\Jobs\ProcessTelegramUpdateJob;
use Modules\Integrations\Infrastructure\Models\IntegrationConnection;
use Modules\Integrations\Infrastructure\Models\WebhookLog;

class WebhookController extends Controller
{
    public function telegram(Request $request): JsonResponse
    {
        if (! $this->verifyTelegramWebhook($request)) {
            return response()->json(['ok' => false], 401);
        }

        $payload = $request->all();

        WebhookLog::query()->create([
            'workspace_id' => $this->webhookLogWorkspaceId(),
            'provider' => 'telegram',
            'event' => $this->telegramEventName($payload),
            'payload' => $payload,
            'status' => 'received',
        ]);

        if (isset($payload['message']) || isset($payload['edited_message'])) {
            if (config('financial.integrations.telegram.inbound_enabled', true)) {
                if (config('financial.integrations.telegram.inbound_sync', true)) {
                    ProcessTelegramUpdateJob::dispatchSync($payload);
                } else {
                    ProcessTelegramUpdateJob::dispatch($payload);
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    public function whatsapp(Request $request): JsonResponse
    {
        if (! $this->verifyEvolutionWebhook($request)) {
            Log::warning('Evolution webhook rejected: invalid apikey');

            return response()->json(['ok' => false], 401);
        }

        $event = (string) ($request->input('event') ?? $request->input('type') ?? 'unknown');
        $payload = $request->all();

        WebhookLog::query()->create([
            'workspace_id' => $this->webhookLogWorkspaceId(),
            'provider' => 'evolution_whatsapp',
            'event' => $event,
            'payload' => $payload,
            'status' => 'received',
        ]);

        $this->syncEvolutionConnectionStatus($event, $payload);

        if ($this->isEvolutionMessageEvent($event) && config('financial.integrations.whatsapp.inbound_enabled', true)) {
            if (config('financial.integrations.whatsapp.inbound_sync', true)) {
                ProcessEvolutionInboundJob::dispatchSync($payload);
            } else {
                ProcessEvolutionInboundJob::dispatch($payload);
            }
        }

        return response()->json(['ok' => true]);
    }

    protected function isEvolutionMessageEvent(string $event): bool
    {
        $normalized = str_replace(['.', '-'], '_', strtolower($event));

        // Apenas mensagens recebidas; send.message é eco do que o bot enviou.
        return str_contains($normalized, 'messages_upsert');
    }

    protected function verifyTelegramWebhook(Request $request): bool
    {
        $secret = config('financial.integrations.telegram.webhook_secret');
        if (empty($secret)) {
            return true;
        }

        $header = $request->header('X-Telegram-Bot-Api-Secret-Token');

        return is_string($header) && hash_equals($secret, $header);
    }

    protected function telegramEventName(array $payload): string
    {
        if (isset($payload['message'])) {
            return 'message';
        }

        if (isset($payload['edited_message'])) {
            return 'edited_message';
        }

        return 'update';
    }

    protected function verifyEvolutionWebhook(Request $request): bool
    {
        $expected = config('financial.integrations.evolution.webhook_secret')
            ?: config('financial.integrations.evolution.api_key');

        if (empty($expected)) {
            return false;
        }

        $provided = $request->header('apikey')
            ?? $request->header('x-api-key')
            ?? $request->query('apikey')
            ?? $request->input('apikey');

        return is_string($provided) && hash_equals((string) $expected, $provided);
    }

    protected function webhookLogWorkspaceId(): ?int
    {
        $configured = (int) config('financial.integrations.evolution.status_workspace_id');
        if ($configured > 0 && Workspace::query()->whereKey($configured)->exists()) {
            return $configured;
        }

        $first = Workspace::query()->orderBy('id')->value('id');

        return is_numeric($first) ? (int) $first : null;
    }

    protected function syncEvolutionConnectionStatus(string $event, array $payload): void
    {
        if (! str_contains(strtolower($event), 'connection')) {
            return;
        }

        $state = strtolower((string) (
            data_get($payload, 'data.state')
            ?? data_get($payload, 'state')
            ?? data_get($payload, 'instance.state')
            ?? 'unknown'
        ));

        $workspaceId = $this->webhookLogWorkspaceId();
        if ($workspaceId === null || $workspaceId < 1) {
            return;
        }

        IntegrationConnection::query()->updateOrCreate(
            [
                'workspace_id' => $workspaceId,
                'provider' => 'evolution_whatsapp',
            ],
            [
                'status' => $state,
                'settings' => [
                    'instance' => config('financial.integrations.evolution.instance_name'),
                    'last_event' => $event,
                ],
                'last_sync_at' => now(),
            ]
        );
    }
}
