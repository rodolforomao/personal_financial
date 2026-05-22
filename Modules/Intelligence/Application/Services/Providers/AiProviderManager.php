<?php

namespace Modules\Intelligence\Application\Services\Providers;

use App\Core\Contracts\AiProviderInterface;
use InvalidArgumentException;

class AiProviderManager
{
    public function driver(?string $name = null): AiProviderInterface
    {
        $name ??= config('financial.ai.default', 'openai');

        return match ($name) {
            'openai' => app(OpenAiProvider::class),
            'openrouter' => app(OpenRouterProvider::class),
            default => throw new InvalidArgumentException("AI provider [{$name}] not supported."),
        };
    }
}
