<?php

namespace App\Core\DTOs;

use Spatie\LaravelData\Data;

class AiResponseData extends Data
{
    public function __construct(
        public string $content,
        public string $provider,
        public string $model,
        public array $usage = [],
        public array $structured = [],
    ) {}
}
