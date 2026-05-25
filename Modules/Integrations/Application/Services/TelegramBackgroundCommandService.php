<?php

namespace Modules\Integrations\Application\Services;

use App\Application\Services\PlatformOperationsGuide;
use App\Models\User;
use Modules\Integrations\Application\Jobs\ProcessTelegramPollJob;
use Modules\Integrations\Application\Jobs\RunTelegramArtisanJob;

class TelegramBackgroundCommandService
{
    public function __construct(
        protected TelegramService $telegram,
        protected PlatformOperationsGuide $operationsGuide,
    ) {}

    /**
     * @return array{handled: bool, reply?: string}|null null = não é comando de background
     */
    public function tryHandle(string $text, User $user, string $chatId): ?array
    {
        $normalized = mb_strtolower(trim($text));
        if (! str_starts_with($normalized, '/')) {
            return null;
        }

        $parts = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $command = $parts[0] ?? '';
        $arg = $parts[1] ?? '';

        return match ($command) {
            '/poll' => $this->dispatchPoll($user, $chatId),
            '/fila', '/queue' => $this->dispatchQueueDrain($user, $chatId),
            '/run', '/exec' => $this->dispatchRunAlias($user, $chatId, $arg),
            '/comandos', '/commands' => $this->listCommands($user, $chatId),
            '/ops', '/status', '/processos' => $this->sendOperationsStatus($user, $chatId),
            default => null,
        };
    }

    /**
     * @return array{handled: bool, reply: string}
     */
    protected function dispatchPoll(User $user, string $chatId): array
    {
        if (! $this->canRunProtected($user, $chatId)) {
            return $this->denied();
        }

        ProcessTelegramPollJob::dispatch($chatId);

        return [
            'handled' => true,
            'reply' => 'poll_dispatched',
        ];
    }

    /**
     * @return array{handled: bool, reply: string}
     */
    protected function dispatchQueueDrain(User $user, string $chatId): array
    {
        if (! $this->canRunProtected($user, $chatId)) {
            return $this->denied();
        }

        $queues = config('financial.integrations.telegram.queue_names', 'notifications,default');

        RunTelegramArtisanJob::dispatch(
            'queue:work',
            [
                '--queue' => $queues,
                '--stop-when-empty' => true,
                '--max-time' => 55,
                '--tries' => 2,
            ],
            $chatId,
            'queue:work (fila)',
        );

        return [
            'handled' => true,
            'reply' => 'queue_dispatched',
        ];
    }

    /**
     * @return array{handled: bool, reply: string}
     */
    protected function dispatchRunAlias(User $user, string $chatId, string $alias): array
    {
        if ($alias === '') {
            if (! $this->canRunProtected($user, $chatId)) {
                return $this->denied();
            }

            return [
                'handled' => true,
                'reply' => 'run_usage',
            ];
        }

        $commands = (array) config('financial.integrations.telegram.background_commands', []);
        if (! isset($commands[$alias])) {
            if (! $this->canRunProtected($user, $chatId)) {
                return $this->denied();
            }

            return [
                'handled' => true,
                'reply' => 'run_unknown',
            ];
        }

        $def = $commands[$alias];
        $isPublic = (bool) ($def['public'] ?? false);

        if (! $isPublic && ! $this->canRunProtected($user, $chatId)) {
            return $this->denied();
        }

        $artisan = (string) ($def['artisan'] ?? '');
        if ($artisan === '' || ! $this->isAllowedArtisan($artisan)) {
            return [
                'handled' => true,
                'reply' => 'run_not_allowed',
            ];
        }

        $parameters = is_array($def['options'] ?? null) ? $def['options'] : [];
        $label = (string) ($def['label'] ?? $alias);

        if ($alias === 'poll') {
            ProcessTelegramPollJob::dispatch($chatId);

            return ['handled' => true, 'reply' => 'poll_dispatched'];
        }

        RunTelegramArtisanJob::dispatch($artisan, $parameters, $chatId, $label);

        return [
            'handled' => true,
            'reply' => 'run_dispatched',
        ];
    }

    /**
     * @return array{handled: bool, reply: string}
     */
    protected function listCommands(User $user, string $chatId): array
    {
        $this->reply($chatId, $this->operationsGuide->commandsForTelegram($this->canRunProtected($user, $chatId)));

        return ['handled' => true, 'reply' => 'commands_list'];
    }

    /**
     * @return array{handled: bool, reply: string}
     */
    protected function sendOperationsStatus(User $user, string $chatId): array
    {
        if (! $this->canRunProtected($user, $chatId)) {
            return $this->denied();
        }

        $this->reply($chatId, $this->operationsGuide->operationsStatus());

        return ['handled' => true, 'reply' => 'ops_status'];
    }

    /**
     * @return array{handled: bool, reply: string}
     */
    protected function denied(): array
    {
        return [
            'handled' => true,
            'reply' => 'denied',
        ];
    }

    public function canRunProtected(User $user, string $chatId): bool
    {
        $adminIds = (array) config('financial.integrations.telegram.admin_chat_ids', []);
        if ($adminIds !== [] && in_array($chatId, $adminIds, true)) {
            return true;
        }

        return (bool) data_get($user->preferences, 'notifications.telegram_admin', false);
    }

    protected function isAllowedArtisan(string $command): bool
    {
        $allowed = (array) config('financial.integrations.telegram.allowed_artisan_commands', []);

        return in_array($command, $allowed, true);
    }

    public function replyForAction(string $replyKey, string $alias = ''): string
    {
        return match ($replyKey) {
            'poll_dispatched' => "⏳ Busca de mensagens iniciada em segundo plano.\n".
                'Você receberá um resumo quando terminar.',
            'queue_dispatched' => "⏳ Processando fila em segundo plano (`queue:work`).\n".
                "Confira o resumo em instantes.",
            'run_dispatched' => "⏳ Comando `{$alias}` enfileirado. Aviso quando concluir.",
            'run_usage' => "Uso: /run <alias>\nEnvie /comandos para ver aliases disponíveis.",
            'run_unknown' => 'Alias desconhecido. Envie /comandos.',
            'run_not_allowed' => 'Comando não permitido neste servidor.',
            'denied' => '⛔ Comando restrito a administradores do Telegram.',
            default => 'Comando enfileirado.',
        };
    }

    protected function reply(string $chatId, string $message): void
    {
        $token = config('financial.integrations.telegram.bot_token');
        if ($token) {
            $this->telegram->send($chatId, $message, $token);
        }
    }
}
