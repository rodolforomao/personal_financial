<?php

namespace Modules\OCR;

use App\Core\Contracts\ModuleInterface;

class OCRModule implements ModuleInterface
{
    public function name(): string
    {
        return 'ocr';
    }

    public function register(): void {}

    public function boot(): void {}
}
