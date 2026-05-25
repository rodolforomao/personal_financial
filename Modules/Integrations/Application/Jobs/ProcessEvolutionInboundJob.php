<?php

namespace Modules\Integrations\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Integrations\Application\Services\WhatsAppInboundService;

class ProcessEvolutionInboundJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public array $payload,
    ) {
        $this->onQueue(config('financial.queues.notifications', 'notifications'));
    }

    public function handle(WhatsAppInboundService $inbound): void
    {
        try {
            $inbound->handlePayload($this->payload);
        } catch (\Throwable $e) {
            Log::error('WhatsApp inbound failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('WhatsApp inbound job failed', ['message' => $exception->getMessage()]);
    }
}
