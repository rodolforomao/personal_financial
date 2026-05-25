<?php

namespace Modules\Integrations\Application\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EvolutionService
{
    protected ?string $lastFailureReason = null;

    public function configured(): bool
    {
        return $this->baseUrl() !== ''
            && $this->apiKey() !== ''
            && $this->instanceName() !== '';
    }

    public function lastFailureReason(): ?string
    {
        return $this->lastFailureReason;
    }

    public function isReady(?string $instance = null): bool
    {
        return $this->connectionState($instance)['state'] === 'open';
    }

    public function sendText(string $number, string $text, ?string $instance = null): bool
    {
        $this->lastFailureReason = null;

        if (! $this->configured()) {
            $this->lastFailureReason = 'Evolution API não configurada no servidor.';
            Log::warning('Evolution API not configured');

            return false;
        }

        $instance = $instance ?? $this->instanceName();
        $state = $this->connectionState($instance)['state'];

        if ($state !== 'open') {
            $this->lastFailureReason = $state === null
                ? 'Não foi possível consultar o status da instância Evolution.'
                : "WhatsApp do servidor não está conectado (status: {$state}). Abra o Evolution Manager, escaneie o QR e aguarde status open.";

            Log::warning('Evolution sendText skipped: instance not open', [
                'instance' => $instance,
                'state' => $state,
            ]);

            return false;
        }

        $number = $this->formatNumber($number);

        try {
            $response = $this->client(sendTimeout: 20)->post("/message/sendText/{$instance}", [
                'number' => $number,
                'text' => $text,
            ]);
        } catch (ConnectionException $e) {
            $this->lastFailureReason = 'Evolution API não respondeu a tempo. Verifique se o container está rodando e a instância está conectada (open).';
            Log::warning('Evolution sendText connection error', [
                'instance' => $instance,
                'message' => $e->getMessage(),
            ]);

            return false;
        } catch (RequestException $e) {
            $this->lastFailureReason = 'Evolution API recusou o envio.';
            Log::warning('Evolution sendText request error', [
                'instance' => $instance,
                'message' => $e->getMessage(),
            ]);

            return false;
        }

        if ($response->failed()) {
            $this->lastFailureReason = 'Evolution API retornou erro ao enviar a mensagem.';
            Log::warning('Evolution sendText failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'instance' => $instance,
            ]);

            return false;
        }

        return true;
    }

    /**
     * @return array{state: string|null, instance: string|null, raw: array<string, mixed>|null}
     */
    public function connectionState(?string $instance = null): array
    {
        if (! $this->configured()) {
            return ['state' => null, 'instance' => null, 'raw' => null];
        }

        $instance = $instance ?? $this->instanceName();

        try {
            $response = $this->client(connectTimeout: 5, sendTimeout: 10)
                ->get("/instance/connectionState/{$instance}");
        } catch (ConnectionException) {
            return ['state' => 'unreachable', 'instance' => $instance, 'raw' => null];
        }

        if ($response->failed()) {
            Log::warning('Evolution connectionState failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['state' => 'error', 'instance' => $instance, 'raw' => null];
        }

        $data = $response->json();
        $state = is_array($data)
            ? ($data['state'] ?? $data['instance']['state'] ?? $data['status'] ?? null)
            : null;

        return [
            'state' => is_string($state) ? strtolower($state) : null,
            'instance' => $instance,
            'raw' => is_array($data) ? $data : null,
        ];
    }

    public function setWebhook(string $url, ?string $instance = null): bool
    {
        if (! $this->configured()) {
            return false;
        }

        $instance = $instance ?? $this->instanceName();

        $webhookKey = (string) (
            config('financial.integrations.evolution.webhook_secret')
            ?: $this->apiKey()
        );

        $response = $this->client()->post("/webhook/set/{$instance}", [
            'webhook' => [
                'enabled' => true,
                'url' => $url,
                'webhookByEvents' => false,
                'webhookBase64' => (bool) config('financial.integrations.evolution.webhook_base64', true),
                'headers' => $webhookKey !== '' ? ['apikey' => $webhookKey] : null,
                'events' => [
                    'CONNECTION_UPDATE',
                    'MESSAGES_UPSERT',
                    'SEND_MESSAGE',
                ],
            ],
        ]);

        if ($response->failed()) {
            Log::warning('Evolution setWebhook failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'instance' => $instance,
            ]);

            return false;
        }

        return true;
    }

    public function instanceExists(?string $instance = null): bool
    {
        if (! $this->configured()) {
            return false;
        }

        $instance = $instance ?? $this->instanceName();
        $response = $this->client()->get('/instance/fetchInstances');

        if ($response->failed()) {
            return false;
        }

        $list = $response->json();
        if (! is_array($list)) {
            return false;
        }

        foreach ($list as $item) {
            $name = is_array($item) ? ($item['name'] ?? $item['instanceName'] ?? null) : null;
            if ($name === $instance) {
                return true;
            }
        }

        return false;
    }

    public function formatNumber(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        return $digits;
    }

    /**
     * Baixa mídia de mensagem recebida via webhook (quando webhookBase64 está desligado).
     */
    public function downloadMessageMedia(array $webhookData): ?string
    {
        if (! $this->configured()) {
            return null;
        }

        $instance = $this->instanceName();
        $messagePayload = [
            'key' => $webhookData['key'] ?? [],
            'message' => $webhookData['message'] ?? [],
        ];

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            if ($attempt > 1) {
                usleep(400_000);
            }

            try {
                $response = $this->client(sendTimeout: 45)->post(
                    "/chat/getBase64FromMediaMessage/{$instance}",
                    ['message' => $messagePayload, 'convertToMp4' => false],
                );
            } catch (\Throwable $e) {
                Log::info('Evolution getBase64FromMediaMessage failed', [
                    'attempt' => $attempt,
                    'message' => $e->getMessage(),
                ]);

                continue;
            }

            if ($response->failed()) {
                Log::info('Evolution getBase64FromMediaMessage HTTP error', [
                    'attempt' => $attempt,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                continue;
            }

            $saved = $this->saveBase64Response($response->json(), $webhookData);
            if ($saved !== null) {
                return $saved;
            }
        }

        return null;
    }

    protected function saveBase64Response(mixed $json, array $webhookData): ?string
    {
        if (! is_array($json)) {
            return null;
        }

        $base64 = $json['base64']
            ?? data_get($json, 'data.base64')
            ?? data_get($json, 'response.base64');

        if (! is_string($base64) || $base64 === '') {
            return null;
        }

        $mime = (string) (
            data_get($webhookData, 'message.documentMessage.mimetype')
            ?? data_get($webhookData, 'message.imageMessage.mimetype')
            ?? 'image/jpeg'
        );
        $binary = base64_decode($base64, true) ?: base64_decode($base64);
        $fileName = (string) data_get($webhookData, 'message.documentMessage.fileName', '');
        [$mime, $ext] = $this->resolveDownloadedMediaType($mime, $fileName, $binary);
        $tmp = sys_get_temp_dir().'/wa_ev_'.uniqid('', true).'.'.$ext;
        file_put_contents($tmp, $binary);

        return is_file($tmp) ? $tmp : null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveDownloadedMediaType(string $mime, string $fileName, string $binary): array
    {
        $lowerMime = strtolower($mime);
        $lowerName = strtolower($fileName);
        $prefix = ltrim(substr($binary, 0, 120));

        if (str_contains($lowerMime, 'xml') || str_ends_with($lowerName, '.xml') || str_starts_with($prefix, '<?xml')) {
            return ['application/xml', 'xml'];
        }

        if (str_contains($lowerMime, 'pdf') || str_ends_with($lowerName, '.pdf') || str_starts_with($prefix, '%PDF')) {
            return ['application/pdf', 'pdf'];
        }

        if (str_contains($lowerMime, 'png') || str_ends_with($lowerName, '.png')) {
            return ['image/png', 'png'];
        }

        if (str_contains($lowerMime, 'webp') || str_ends_with($lowerName, '.webp')) {
            return ['image/webp', 'webp'];
        }

        return [$mime ?: 'image/jpeg', 'jpg'];
    }

    protected function client(int $connectTimeout = 5, int $sendTimeout = 15): PendingRequest
    {
        $sendTimeout = $sendTimeout > 0
            ? $sendTimeout
            : (int) config('financial.integrations.evolution.timeout', 30);

        return Http::baseUrl($this->baseUrl())
            ->withHeaders(['apikey' => $this->apiKey()])
            ->connectTimeout($connectTimeout)
            ->timeout($sendTimeout)
            ->acceptJson();
    }

    protected function baseUrl(): string
    {
        return rtrim((string) config('financial.integrations.evolution.api_url'), '/');
    }

    protected function apiKey(): string
    {
        return (string) config('financial.integrations.evolution.api_key');
    }

    protected function instanceName(): string
    {
        return (string) config('financial.integrations.evolution.instance_name');
    }
}
