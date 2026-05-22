<?php

namespace App\Core\Contracts;

use App\Core\DTOs\AiRequestData;
use App\Core\DTOs\AiResponseData;

interface AiProviderInterface
{
    public function name(): string;

    public function isEnabled(): bool;

    public function complete(AiRequestData $request): AiResponseData;
}
