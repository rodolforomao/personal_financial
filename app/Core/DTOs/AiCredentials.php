<?php

namespace App\Core\DTOs;

readonly class AiCredentials
{
    public function __construct(
        public string $provider,
        public string $apiKey,
        public ?string $model = null,
        public string $source = 'system',
        public bool $isBillable = false,
    ) {}
}
