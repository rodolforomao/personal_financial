<?php

namespace App\Core\DTOs;

use Spatie\LaravelData\Data;

class AiRequestData extends Data
{
    public function __construct(
        public string $prompt,
        public string $context = 'financial',
        public ?string $systemPrompt = null,
        public array $metadata = [],
        public float $temperature = 0.2,
        public int $maxTokens = 4096,
    ) {}
}
