<?php

namespace Modules\OCR\Application\Services;

use App\Core\Contracts\OcrProviderInterface;
use Modules\OCR\Application\Services\Providers\TesseractOcrProvider;
use Modules\OCR\Application\Services\Providers\VisionApiOcrProvider;

class OcrProviderManager
{
    public function driver(?string $name = null): OcrProviderInterface
    {
        $name ??= config('financial.ocr.default', 'tesseract');

        return match ($name) {
            'tesseract' => app(TesseractOcrProvider::class),
            'vision' => app(VisionApiOcrProvider::class),
            default => app(TesseractOcrProvider::class),
        };
    }
}
