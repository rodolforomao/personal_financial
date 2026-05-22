<?php

namespace Modules\Intelligence\Application\Services;

use App\Core\DTOs\AiRequestData;
use App\Core\Support\FeatureFlag;
use Illuminate\Support\Facades\DB;
use Modules\Intelligence\Application\Services\Providers\AiProviderManager;
use Modules\OCR\Infrastructure\Models\OcrJob;

class ObservabilityIntelligenceService
{
    public function __construct(protected AiProviderManager $providers) {}

    public function analyze(int $workspaceId): array
    {
        if (! FeatureFlag::enabled('ai_observability', $workspaceId)) {
            return [];
        }

        $metrics = [
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'ocr_queue_backlog' => OcrJob::query()->where('status', 'queued')->count(),
            'ocr_failed' => OcrJob::query()->where('status', 'failed')->where('created_at', '>=', now()->subDay())->count(),
        ];

        $logs = $this->recentLogs();

        $response = $this->providers->driver()->complete(
            new AiRequestData(
                prompt: 'Analyze operational health and suggest fixes.',
                context: 'observability',
                systemPrompt: config('financial.ai_prompts.observability'),
                metadata: ['metrics' => $metrics, 'logs' => $logs],
            )
        );

        return json_decode($response->content, true) ?? ['summary' => $response->content];
    }

    protected function recentLogs(): array
    {
        $path = storage_path('logs/laravel.log');
        if (! file_exists($path)) {
            return [];
        }

        $lines = array_slice(file($path) ?: [], -100);

        return array_map('trim', $lines);
    }
}
