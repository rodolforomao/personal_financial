<?php

namespace Modules\OCR\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\OCR\Application\Services\OcrService;
use Modules\OCR\Infrastructure\Models\OcrJob;

class ProcessOcrJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $ocrJobId) {}

    public function handle(OcrService $service): void
    {
        $ocrJob = OcrJob::query()->findOrFail($this->ocrJobId);
        $service->process($ocrJob);
    }
}
