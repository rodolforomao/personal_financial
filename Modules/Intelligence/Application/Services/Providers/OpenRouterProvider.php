<?php

namespace Modules\Intelligence\Application\Services\Providers;

use App\Core\Contracts\AiProviderInterface;
use App\Core\DTOs\AiRequestData;
use App\Core\DTOs\AiResponseData;
use App\Core\Exceptions\AiUnavailableException;
use Illuminate\Support\Facades\Http;

class OpenRouterProvider implements AiProviderInterface
{
    public function name(): string
    {
        return 'openrouter';
    }

    public function isEnabled(): bool
    {
        return ! empty(config('financial.ai.openrouter.api_key'));
    }

    public function complete(AiRequestData $request): AiResponseData
    {
        $apiKey = $request->metadata['api_key'] ?? config('financial.ai.openrouter.api_key');
        if (empty($apiKey)) {
            throw AiUnavailableException::notConfigured();
        }

        $response = Http::withToken($apiKey)
            ->withHeaders(['HTTP-Referer' => config('app.url')])
            ->timeout(120)
            ->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => $request->metadata['model'] ?? config('financial.ai.openrouter.model', 'openai/gpt-4o-mini'),
                'messages' => [
                    ['role' => 'system', 'content' => $request->systemPrompt ?? 'You are a financial analyst.'],
                    ['role' => 'user', 'content' => $request->prompt],
                ],
                'temperature' => $request->temperature,
            ]);

        if ($response->status() === 401) {
            throw AiUnavailableException::invalidKey();
        }

        if ($response->failed()) {
            throw AiUnavailableException::providerError($response->json('error.message') ?? $response->body());
        }

        $body = $response->json();
        $content = $body['choices'][0]['message']['content'] ?? '';

        return new AiResponseData(
            content: $content,
            provider: $this->name(),
            model: $body['model'] ?? 'openrouter',
            usage: $body['usage'] ?? [],
        );
    }
}
