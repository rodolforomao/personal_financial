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

        $response = $this->client()->post("/webhook/set/{$instance}", [
            'webhook' => [
                'enabled' => true,
                'url' => $url,
                'webhookByEvents' => false,
                'webhookBase64' => false,
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
