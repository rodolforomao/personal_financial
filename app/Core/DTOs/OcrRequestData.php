<?php

namespace App\Core\DTOs;

use Spatie\LaravelData\Data;

class OcrRequestData extends Data
{
    public function __construct(
        public string $filePath,
        public string $mimeType,
        public int $workspaceId,
        public ?int $documentId = null,
        public string $source = 'upload',
    ) {}
}
