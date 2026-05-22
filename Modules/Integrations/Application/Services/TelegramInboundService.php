<?php

namespace Modules\Integrations\Application\Services;

use App\Core\Enums\TransactionStatus;
use App\Core\Enums\TransactionType;
use App\Models\User;
use Illuminate\Support\Facades\Log;
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

        $text = trim((string) ($message['text'] ?? ''));
        $chatId = (string) ($message['chat']['id'] ?? '');
        $messageId = (string) ($message['message_id'] ?? '');

        if ($text === '' || $chatId === '') {
            return ['handled' => false];
        }

        if (! empty($message['from']['is_bot'])) {
            return ['handled' => false];
        }

        $token = config('financial.integrations.telegram.bot_token');
        if (! $token) {
            return ['handled' => false];
        }

        if ($this->isCommand($text, '/start') || $this->isCommand($text, '/help')) {
            $this->telegram->send($chatId, $this->helpMessage(), $token);

            return ['handled' => true, 'reply' => 'help'];
        }

        $user = $this->resolveUserByChatId($chatId);
        if (! $user) {
            $this->telegram->send(
                $chatId,
                "Não encontrei sua conta. Configure Telegram em:\n".config('app.url')."/integrations/notifications\n".
                "e envie /start ao bot @".config('financial.integrations.telegram.bot_username', 'bot').'.',
                $token
            );

            return ['handled' => true, 'reply' => 'unlinked'];
        }

        $intent = $this->parser->parse($text);
        if (! $intent) {
            $this->telegram->send(
                $chatId,
                "Não entendi o lançamento. Exemplo:\n".
                "• Gasto de 16.000 aporte sociedade Multfilmes GYN\n".
                "• Receita de 5.000 consultoria maio\n\n".
                'Envie /help para mais exemplos.',
                $token
            );

            return ['handled' => true, 'reply' => 'unparsed'];
        }

        $workspaceId = (int) $user->workspaces()->value('workspaces.id');
        if ($workspaceId < 1) {
            $this->telegram->send($chatId, 'Sua conta não tem workspace ativo.', $token);

            return ['handled' => true, 'reply' => 'no_workspace'];
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
            $this->telegram->send(
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

        $this->telegram->send(
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
            ->where('preferences->notifications->telegram_chat_id', $chatId)
            ->first();
    }

    protected function isCommand(string $text, string $command): bool
    {
        return str_starts_with(mb_strtolower(trim($text)), $command);
    }

    protected function helpMessage(): string
    {
        return "Olá! Sou o assistente financeiro.\n\n".
            "Envie lançamentos em texto livre, por exemplo:\n".
            "• Gasto de 16.000 aporte sociedade Multfilmes GYN\n".
            "• Despesa 250,50 almoço equipe\n".
            "• Receita de 5.000 consultoria\n\n".
            "Requisitos:\n".
            "1. Conta vinculada em /integrations/notifications\n".
            "2. Valor com centavos opcional (16.000 ou 250,50)\n\n".
            'Duplicatas (mesmo dia, valor e descrição) são ignoradas.';
    }
}
