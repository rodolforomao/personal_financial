<?php

namespace Modules\Integrations\Application\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected ?string $lastFailureReason = null;

    public function __construct(
        protected EvolutionService $evolution,
    ) {}

    public function lastFailureReason(): ?string
    {
        return $this->lastFailureReason ?? $this->evolution->lastFailureReason();
    }

    public function send(string $phone, string $message, ?string $apiUrl = null, ?string $token = null): bool
    {
        if ($this->shouldUseEvolution()) {
            return $this->evolution->sendText($phone, $message);
        }

        $url = $apiUrl ?? config('financial.integrations.whatsapp.api_url');
        $apiToken = $token ?? config('financial.integrations.whatsapp.token');

        if (empty($url) || empty($apiToken)) {
            Log::warning('WhatsApp integration not configured');

            return false;
        }

        $response = Http::withToken($apiToken)->post($url, [
            'to' => $phone,
            'message' => $message,
        ]);

        if ($response->failed()) {
            Log::warning('WhatsApp send failed', ['body' => $response->body()]);

            return false;
        }

        return true;
    }

    public function sendWithConfig(array $config, string $message): bool
    {
        $this->lastFailureReason = null;

        if ($this->configUsesEvolution($config)) {
            $ok = $this->evolution->sendText(
                $config['phone'],
                $message,
                $config['instance'] ?? null
            );
            $this->lastFailureReason = $this->evolution->lastFailureReason();

            return $ok;
        }

        return $this->send(
            $config['phone'],
            $message,
            $config['api_url'] ?? null,
            $config['token'] ?? null
        );
    }

    protected function shouldUseEvolution(): bool
    {
        return config('financial.integrations.whatsapp.provider') === 'evolution'
            && $this->evolution->configured();
    }

    protected function configUsesEvolution(array $config): bool
    {
        if (($config['provider'] ?? null) === 'evolution') {
            return $this->evolution->configured();
        }

        if ($this->shouldUseEvolution() && ($config['source'] ?? '') === 'system') {
            return true;
        }

        return false;
    }
}
