<?php

namespace Modules\Integrations\Application\Services;

use App\Application\Services\PlatformOperationsGuide;
use App\Core\Enums\TransactionStatus;
use App\Core\Enums\TransactionType;
use App\Core\Support\NotificationDestinationNormalizer;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Finance\Application\Actions\CreateTransactionAction;
use Modules\Finance\Application\DTOs\CreateTransactionData;
use Modules\Finance\Application\Services\TransactionDeduplicationService;

class TelegramInboundService
{
    public function __construct(
        protected TelegramTransactionIntentParser $parser,
        protected TelegramService $telegram,
        protected CreateTransactionAction $createTransaction,
        protected TransactionDeduplicationService $deduplication,
        protected InboundReceiptFlowService $receiptFlow,
        protected ReceiptConfirmationService $receiptConfirmation,
        protected TelegramBackgroundCommandService $backgroundCommands,
        protected PlatformOperationsGuide $operationsGuide,
    ) {}

    /**
     * @return array{handled: bool, reply?: string}
     */
    public function handleUpdate(array $update): array
    {
        $message = $update['message'] ?? $update['edited_message'] ?? null;
        if (! is_array($message)) {
            return ['handled' => false];
        }

        $chatId = (string) ($message['chat']['id'] ?? '');
        if ($chatId === '' || ! empty($message['from']['is_bot'])) {
            return ['handled' => false];
        }

        $token = config('financial.integrations.telegram.bot_token');
        if (! $token) {
            return ['handled' => false];
        }

        $text = trim((string) ($message['text'] ?? ''));
        $messageId = (string) ($message['message_id'] ?? '');
        $user = $this->resolveUserForInboundMessage($message, $chatId);

        if ($text !== '' && ($this->isCommand($text, '/start') || $this->isCommand($text, '/help'))) {
            $this->reply(
                $chatId,
                $user
                    ? $this->helpMessage()
                    : "Não encontrei sua conta. Configure Telegram em:\n".config('app.url')."/integrations/notifications\n".
                        'e envie /start ao bot @'.config('financial.integrations.telegram.bot_username', 'bot').'.',
                $token
            );

            return ['handled' => true, 'reply' => 'help'];
        }

        if ($text !== '' && ($this->isCommand($text, '/comandos') || $this->isCommand($text, '/commands'))) {
            if ($user) {
                $this->backgroundCommands->tryHandle($text, $user, $chatId);
            } else {
                $this->reply($chatId, "Vincule sua conta em:\n".config('app.url').'/integrations/notifications', $token);
            }

            return ['handled' => true, 'reply' => 'commands_list'];
        }

        if (isset($message['contact'])) {
            $this->reply(
                $chatId,
                $user
                    ? 'Telegram vinculado. Agora você pode voltar ao painel e clicar em Testar Telegram.'
                    : 'Não encontrei sua conta por esse telefone. Confira o telefone salvo no painel ou use o código numérico do @userinfobot.',
                $token
            );

            return ['handled' => true, 'reply' => $user ? 'contact_linked' : 'contact_unlinked'];
        }

        if (! $user) {
            if ($text !== '' || $this->hasInboundDocument($message)) {
                $this->reply(
                    $chatId,
                    "Não encontrei sua conta. Configure Telegram em:\n".config('app.url')."/integrations/notifications\n".
                    'e envie /start ao bot @'.config('financial.integrations.telegram.bot_username', 'bot').'.',
                    $token
                );
            }

            return ['handled' => true, 'reply' => 'unlinked'];
        }

        $workspaceId = (int) $user->workspaces()->value('workspaces.id');
        if ($workspaceId < 1) {
            $this->reply($chatId, 'Sua conta não tem workspace ativo.', $token);

            return ['handled' => true, 'reply' => 'no_workspace'];
        }

        if ($text !== '') {
            $background = $this->backgroundCommands->tryHandle($text, $user, $chatId);
            if ($background !== null) {
                if (($background['reply'] ?? '') !== 'commands_list') {
                    $alias = $this->extractRunAlias($text);
                    $this->reply(
                        $chatId,
                        $this->backgroundCommands->replyForAction($background['reply'] ?? '', $alias),
                        $token,
                    );
                }

                return ['handled' => true, 'reply' => $background['reply'] ?? 'background'];
            }

            $confirm = $this->receiptFlow->handleConfirmationText($user, 'telegram', $chatId, $text);
            if ($confirm) {
                $this->reply($chatId, $confirm['reply'], $token);

                return ['handled' => true, 'reply' => $confirm['reply'] === 'receipt_confirm' ? 'receipt_confirm' : 'receipt_supplement'];
            }
        }

        $fileId = $this->extractInboundFileId($message);
        if ($fileId) {
            $download = $this->telegram->downloadFile($fileId, $token);
            if (! ($download['ok'] ?? false)) {
                $this->reply($chatId, $download['error'] ?? 'Falha ao baixar imagem.', $token);

                return ['handled' => true, 'reply' => 'download_failed'];
            }

            $caption = trim((string) ($message['caption'] ?? ''));
            $fileName = (string) ($message['document']['file_name'] ?? '');
            $this->reply($chatId, '⏳ Lendo comprovante...', $token);
            $flow = $this->receiptFlow->handleMedia(
                $user,
                $workspaceId,
                'telegram',
                $chatId,
                $messageId,
                $download['path'],
                $download['mime'] ?? 'image/jpeg',
                $caption !== '' ? $caption : null,
                $fileName !== '' ? $fileName : null,
            );
            $this->reply($chatId, $flow['reply'], $token);
            if (isset($download['path']) && is_file($download['path'])) {
                @unlink($download['path']);
            }

            return ['handled' => true, 'reply' => 'receipt_draft'];
        }

        if ($text === '') {
            return ['handled' => false];
        }

        return $this->handleTextTransaction($user, $workspaceId, $chatId, $messageId, $text, $token);
    }

    /**
     * @return array{handled: bool, reply?: string}
     */
    protected function handleTextTransaction(
        User $user,
        int $workspaceId,
        string $chatId,
        string $messageId,
        string $text,
        string $token,
    ): array {
        if ($this->receiptConfirmation->findPendingDraft($user, 'telegram', $chatId)) {
            $this->reply(
                $chatId,
                "Você tem um comprovante aguardando confirmação.\nResponda SIM ou NÃO antes de outro lançamento.",
                $token
            );

            return ['handled' => true, 'reply' => 'pending_receipt'];
        }

        $intent = $this->parser->parse($text);
        if (! $intent) {
            $this->reply(
                $chatId,
                "Não entendi. Envie:\n".
                "• Foto/PDF/XML de comprovante ou nota fiscal (confirmamos antes de salvar)\n".
                "• Texto: Gasto de 16.000 descrição\n".
                '• /help',
                $token
            );

            return ['handled' => true, 'reply' => 'unparsed'];
        }

        $date = now()->toDateString();
        $fingerprint = hash('sha256', implode('|', [
            $chatId,
            $messageId,
            $intent['type']->value,
            (string) $intent['amount'],
            mb_strtolower($intent['description']),
        ]));

        if ($this->deduplication->exists(
            $workspaceId,
            $intent['type'],
            $intent['amount'],
            $date,
            $intent['description'],
            $fingerprint,
        )) {
            $this->reply(
                $chatId,
                'ℹ️ Esse lançamento já parece existir na base (mesmo valor, data e descrição). Nada foi duplicado.',
                $token
            );

            return ['handled' => true, 'reply' => 'duplicate'];
        }

        $transaction = $this->createTransaction->execute(new CreateTransactionData(
            workspaceId: $workspaceId,
            type: $intent['type'],
            amount: $intent['amount'],
            description: $intent['description'],
            transactionDate: $date,
            status: TransactionStatus::Pending,
            source: 'telegram',
            metadata: [
                'telegram_chat_id' => $chatId,
                'telegram_message_id' => $messageId,
                'telegram_fingerprint' => $fingerprint,
                'telegram_raw' => $text,
            ],
        ));

        $label = $intent['type'] === TransactionType::Income ? 'Receita' : 'Gasto';
        $amountFormatted = 'R$ '.number_format($intent['amount'], 2, ',', '.');

        $this->reply(
            $chatId,
            "✅ {$label} registrado (#{$transaction->id})\n".
            "Valor: {$amountFormatted}\n".
            "Descrição: {$intent['description']}\n".
            'Data: '.now()->format('d/m/Y'),
            $token
        );

        Log::info('Telegram transaction created', [
            'transaction_id' => $transaction->id,
            'user_id' => $user->id,
            'workspace_id' => $workspaceId,
        ]);

        return ['handled' => true, 'reply' => 'created'];
    }

    protected function resolveUserByChatId(string $chatId): ?User
    {
        return User::query()
            ->where(function ($q) use ($chatId) {
                $q->where('preferences->notifications->telegram_chat_id', $chatId)
                    ->orWhere('preferences->notifications->telegram_chat_id', '"'.$chatId.'"');
            })
            ->first();
    }

    protected function resolveUserForInboundMessage(array $message, string $chatId): ?User
    {
        $user = $this->resolveUserByChatId($chatId);
        if ($user) {
            return $user;
        }

        $user = $this->resolveUserByTelegramIdentity($message);
        if (! $user) {
            return null;
        }

        $this->persistTelegramChatId($user, $chatId);

        return $user->refresh();
    }

    protected function resolveUserByTelegramIdentity(array $message): ?User
    {
        $username = $this->telegramUsernameFromMessage($message);
        $phone = $this->telegramPhoneFromMessage($message);
        if (! $username && ! $phone) {
            return null;
        }

        $target = $username ? '@'.$username : null;
        $user = User::query()
            ->where(function ($q) use ($phone, $target) {
                if ($target) {
                    $q->orWhere('preferences->notifications->telegram_destination_display', $target)
                        ->orWhere('preferences->notifications->telegram_chat_id', $target);
                }

                if ($phone) {
                    $q->orWhere('preferences->notifications->telegram_destination_display', $phone)
                        ->orWhere('preferences->notifications->telegram_chat_id', $phone);
                }
            })
            ->first();

        if ($user) {
            return $user;
        }

        return User::query()
            ->where(function ($q) {
                $q->whereNotNull('preferences->notifications->telegram_destination_display')
                    ->orWhereNotNull('preferences->notifications->telegram_chat_id');
            })
            ->get()
            ->first(function (User $candidate) use ($phone, $target): bool {
                $notifications = $candidate->preferences['notifications'] ?? [];

                $storedDestinations = [
                    NotificationDestinationNormalizer::telegram($notifications['telegram_destination_display'] ?? null),
                    NotificationDestinationNormalizer::telegram($notifications['telegram_chat_id'] ?? null),
                ];

                return ($target && in_array($target, $storedDestinations, true))
                    || ($phone && in_array($phone, $storedDestinations, true));
            });
    }

    protected function telegramUsernameFromMessage(array $message): ?string
    {
        $chat = $message['chat'] ?? [];
        if (($chat['type'] ?? '') !== 'private') {
            return null;
        }

        $username = $message['from']['username'] ?? $chat['username'] ?? null;
        if (! is_string($username) || trim($username) === '') {
            return null;
        }

        return Str::lower(ltrim(trim($username), '@'));
    }

    protected function telegramPhoneFromMessage(array $message): ?string
    {
        $chat = $message['chat'] ?? [];
        if (($chat['type'] ?? '') !== 'private') {
            return null;
        }

        $phone = $message['contact']['phone_number'] ?? null;
        if (! is_string($phone) || trim($phone) === '') {
            return null;
        }

        return NotificationDestinationNormalizer::telegramPhone($phone);
    }

    protected function persistTelegramChatId(User $user, string $chatId): void
    {
        $prefs = $user->preferences ?? [];
        $notifications = $prefs['notifications'] ?? [];

        if (($notifications['telegram_chat_id'] ?? null) === $chatId) {
            return;
        }

        $notifications['telegram_chat_id'] = $chatId;
        $prefs['notifications'] = $notifications;
        $user->forceFill(['preferences' => $prefs])->save();
    }

    protected function hasInboundDocument(array $message): bool
    {
        return $this->extractInboundFileId($message) !== null;
    }

    protected function extractInboundFileId(array $message): ?string
    {
        if (! empty($message['photo']) && is_array($message['photo'])) {
            $largest = end($message['photo']);

            return $largest['file_id'] ?? null;
        }

        $document = $message['document'] ?? null;
        if (is_array($document)) {
            $mime = strtolower((string) ($document['mime_type'] ?? ''));
            $fileName = strtolower((string) ($document['file_name'] ?? ''));
            if (str_starts_with($mime, 'image/')
                || $mime === 'application/pdf'
                || str_contains($mime, 'xml')
                || str_ends_with($fileName, '.pdf')
                || str_ends_with($fileName, '.xml')
                || $mime === ''
                || $mime === 'application/octet-stream') {
                return $document['file_id'] ?? null;
            }
        }

        return null;
    }

    protected function isCommand(string $text, string $command): bool
    {
        return str_starts_with(mb_strtolower(trim($text)), $command);
    }

    protected function extractRunAlias(string $text): string
    {
        $parts = preg_split('/\s+/', mb_strtolower(trim($text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $parts[1] ?? '';
    }

    protected function reply(string $chatId, string $message, string $token): void
    {
        $result = $this->telegram->send($chatId, $message, $token);
        if (! ($result['ok'] ?? false)) {
            Log::error('Telegram reply failed', [
                'chat_id' => $chatId,
                'error' => $result['error'] ?? 'unknown',
            ]);
        }
    }

    protected function helpMessage(): string
    {
        return $this->operationsGuide->helpIntro();
    }
}
