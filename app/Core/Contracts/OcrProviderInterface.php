<?php

namespace App\Core\Contracts;

use App\Core\DTOs\OcrRequestData;
use App\Core\DTOs\OcrResultData;

interface OcrProviderInterface
{
    public function name(): string;

    public function extract(OcrRequestData $request): OcrResultData;
}
