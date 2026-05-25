<?php

namespace Modules\Integrations\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Modules\Integrations\Application\Services\TelegramService;
use Symfony\Component\Console\Output\BufferedOutput;

class RunTelegramArtisanJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(
        public string $command,
        public array $parameters = [],
        public ?string $notifyChatId = null,
        public string $label = '',
    ) {
        $this->onQueue(config('financial.queues.notifications', 'notifications'));
    }

    public function handle(TelegramService $telegram): void
    {
        $output = new BufferedOutput;
        $exitCode = Artisan::call($this->command, $this->parameters, $output);
        $text = trim($output->fetch());
        $snippet = mb_substr($text !== '' ? $text : '(sem saída)', 0, 3500);

        Log::info('Telegram artisan job finished', [
            'command' => $this->command,
            'exit_code' => $exitCode,
        ]);

        if ($this->notifyChatId === null) {
            return;
        }

        $token = config('financial.integrations.telegram.bot_token');
        if (! $token) {
            return;
        }

        $label = $this->label !== '' ? $this->label : $this->command;
        $status = $exitCode === 0 ? '✅' : '⚠️';
        $message = "{$status} `{$label}` finalizado (código {$exitCode})\n\n```\n{$snippet}\n```";

        $telegram->send($this->notifyChatId, $message, $token);
    }

    public function failed(\Throwable $exception): void
    {
        if ($this->notifyChatId === null) {
            return;
        }

        $token = config('financial.integrations.telegram.bot_token');
        if (! $token) {
            return;
        }

        app(TelegramService::class)->send(
            $this->notifyChatId,
            '⚠️ Comando `'.$this->label.'" falhou: '.mb_substr($exception->getMessage(), 0, 200),
            $token,
        );
    }
}
