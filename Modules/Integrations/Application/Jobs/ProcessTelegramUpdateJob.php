<?php

namespace Modules\Integrations\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Integrations\Application\Services\TelegramInboundService;

class ProcessTelegramUpdateJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public array $update,
    ) {
        $this->onQueue(config('financial.queues.notifications', 'notifications'));
    }

    public function handle(TelegramInboundService $inbound): void
    {
        $inbound->handleUpdate($this->update);
    }
}
