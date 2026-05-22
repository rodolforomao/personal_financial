<?php

namespace Modules\OCR\Application\Services;

use App\Core\DTOs\OcrRequestData;
use Modules\OCR\Application\Jobs\ProcessOcrJob;
use Modules\OCR\Infrastructure\Models\Document;
use Modules\OCR\Infrastructure\Models\OcrJob;

class OcrService
{
    public function queueDocument(Document $document): OcrJob
    {
        $job = OcrJob::query()->create([
            'document_id' => $document->id,
            'status' => 'queued',
            'provider' => config('financial.ocr.default'),
        ]);

        ProcessOcrJob::dispatch($job->id)->onQueue('ocr');

        return $job;
    }

    public function process(OcrJob $ocrJob): void
    {
        $document = $ocrJob->document;
        $ocrJob->update(['status' => 'processing', 'started_at' => now()]);

        try {
            $provider = app(OcrProviderManager::class)->driver($ocrJob->provider);
            $result = $provider->extract(new OcrRequestData(
                filePath: storage_path('app/'.$document->storage_path),
                mimeType: $document->mime_type,
                workspaceId: $document->workspace_id,
                documentId: $document->id,
            ));

            $document->update([
                'status' => 'processed',
                'ocr_result' => $result->toArray(),
            ]);

            $ocrJob->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $ocrJob->update([
                'status' => 'failed',
                'attempts' => $ocrJob->attempts + 1,
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
