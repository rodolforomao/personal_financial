<?php

namespace App\Core\DTOs;

use Spatie\LaravelData\Data;

class OcrResultData extends Data
{
    public function __construct(
        public string $rawText,
        public array $entities = [],
        public ?float $amount = null,
        public ?string $currency = 'BRL',
        public ?string $date = null,
        public ?string $counterparty = null,
        public ?string $bank = null,
        public ?string $suggestedCategory = null,
        public float $confidence = 0.0,
    ) {}
}
