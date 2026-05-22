<?php

namespace Modules\Intelligence\Application\Services\Providers;

use App\Core\Contracts\AiProviderInterface;
use App\Core\DTOs\AiRequestData;
use App\Core\DTOs\AiResponseData;
use App\Core\Exceptions\AiUnavailableException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OpenAiProvider implements AiProviderInterface
{
    public function name(): string
    {
        return 'openai';
    }

    public function isEnabled(): bool
    {
        return ! empty(config('financial.ai.openai.api_key'));
    }

    public function complete(AiRequestData $request): AiResponseData
    {
        $apiKey = $request->metadata['api_key'] ?? config('financial.ai.openai.api_key');
        if (empty($apiKey)) {
            throw AiUnavailableException::notConfigured();
        }

        $this->guardPrompt($request->prompt);

        $response = Http::withToken($apiKey)
            ->timeout(120)
            ->post($this->chatCompletionsUrl(), [
                'model' => $request->metadata['model'] ?? config('financial.ai.openai.model', 'gpt-4o-mini'),
                'messages' => array_values(array_filter([
                    $request->systemPrompt ? ['role' => 'system', 'content' => $request->systemPrompt] : null,
                    ['role' => 'user', 'content' => $request->prompt],
                ])),
                'temperature' => $request->temperature,
                'max_tokens' => $request->maxTokens,
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
            model: $body['model'] ?? config('financial.ai.openai.model'),
            usage: $body['usage'] ?? [],
            structured: $this->tryParseJson($content),
        );
    }

    protected function chatCompletionsUrl(): string
    {
        return config('financial.ai.openai.base_url').'/chat/completions';
    }

    protected function guardPrompt(string $prompt): void
    {
        $blocked = ['ignore previous', 'execute shell', 'run command', '<?php', 'eval('];
        foreach ($blocked as $pattern) {
            if (Str::contains(Str::lower($prompt), $pattern)) {
                abort(422, 'Prompt rejected for security reasons.');
            }
        }
    }

    protected function tryParseJson(string $content): array
    {
        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }
}
