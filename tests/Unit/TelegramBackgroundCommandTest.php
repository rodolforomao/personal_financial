<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Modules\Integrations\Application\Jobs\ProcessTelegramPollJob;
use Modules\Integrations\Application\Jobs\RunTelegramArtisanJob;
use Modules\Integrations\Application\Services\TelegramBackgroundCommandService;
use Tests\TestCase;

class TelegramBackgroundCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_poll_command_is_public(): void
    {
        $user = new User;
        $user->preferences = ['notifications' => []];

        $service = app(TelegramBackgroundCommandService::class);
        $result = $service->tryHandle('/poll', $user, '999');

        $this->assertNotNull($result);
        $this->assertTrue($result['handled']);
        $this->assertSame('poll_dispatched', $result['reply']);
        Queue::assertPushed(ProcessTelegramPollJob::class);
    }

    public function test_queue_command_denied_without_admin(): void
    {
        config(['financial.integrations.telegram.admin_chat_ids' => []]);

        $user = new User;
        $user->preferences = ['notifications' => []];

        $service = app(TelegramBackgroundCommandService::class);
        $result = $service->tryHandle('/fila', $user, '999');

        $this->assertSame('denied', $result['reply'] ?? null);
    }

    public function test_queue_allowed_for_admin_chat_id(): void
    {
        config(['financial.integrations.telegram.admin_chat_ids' => ['12345']]);

        $user = new User;
        $service = app(TelegramBackgroundCommandService::class);
        $result = $service->tryHandle('/fila', $user, '12345');

        $this->assertSame('queue_dispatched', $result['reply'] ?? null);
        Queue::assertPushed(RunTelegramArtisanJob::class);
    }
}
