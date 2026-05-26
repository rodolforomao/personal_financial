<?php

namespace Modules\Integrations\Application\Services;

use App\Core\Enums\TransactionStatus;
use App\Core\Enums\TransactionType;
use App\Core\Support\NotificationDestinationNormalizer;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Finance\Application\Actions\CreateTransactionAction;
use Modules\Finance\Application\DTOs\CreateTransactionData;
use Modules\Finance\Application\Services\StatementImportWorkflowService;
use Modules\Finance\Application\Services\TransactionDeduplicationService;

class WhatsAppInboundService
{
    public function __construct(
        protected WhatsAppService $whatsapp,
        protected TelegramTransactionIntentParser $parser,
        protected InboundReceiptFlowService $receiptFlow,
        protected ReceiptConfirmationService $receiptConfirmation,
        protected CreateTransactionAction $createTransaction,
        protected TransactionDeduplicationService $deduplication,
        protected WhatsAppSenderPhoneResolver $phoneResolver,
        protected StatementImportWorkflowService $statementImports,
    ) {}

    /**
     * @return array{handled: bool, reply?: string}
     */
    public function handlePayload(array $payload): array
    {
        if (! config('financial.integrations.whatsapp.inbound_enabled', true)) {
            return ['handled' => false];
        }

        $parsed = $this->parseEvolutionMessage($payload);
        if (! $parsed) {
            return ['handled' => false];
        }

        $phone = NotificationDestinationNormalizer::whatsapp($parsed['phone']);
        if (! $phone) {
            return ['handled' => false];
        }

        $user = $this->resolveUserByPhone($phone);
        if (! $user) {
            if ($parsed['text'] || $parsed['media_path'] || ($parsed['inbound_media_missing'] ?? false)) {
                $this->reply($phone, "Conta não vinculada. Configure seu WhatsApp em:\n".config('app.url').'/integrations/notifications');
            }

            return ['handled' => true, 'reply' => 'unlinked'];
        }

        $workspaceId = (int) $user->workspaces()->value('workspaces.id');
        if ($workspaceId < 1) {
            $this->reply($phone, 'Sua conta não tem workspace ativo.');

            return ['handled' => true, 'reply' => 'no_workspace'];
        }

        $chatId = $phone;

        if ($parsed['text'] !== '') {
            $confirm = $this->receiptFlow->handleConfirmationText($user, 'whatsapp', $chatId, $parsed['text']);
            if ($confirm) {
                $this->reply($phone, $confirm['reply']);
                $replyKind = str_contains($confirm['reply'], 'Atualizei') ? 'receipt_supplement' : 'receipt_confirm';

                return ['handled' => true, 'reply' => $replyKind];
            }
        }

        if ($parsed['inbound_media_missing'] ?? false) {
            $this->reply(
                $phone,
                "Não consegui baixar o comprovante.\n".
                "• Tente reenviar o arquivo pelo WhatsApp\n".
                "• Se preferir, envie o comprovante pelo Telegram\n".
                '• Texto continua funcionando: Gasto de 100 descrição'
            );

            return ['handled' => true, 'reply' => 'media_download_failed'];
        }

        if ($parsed['media_path']) {
            $statementImport = $this->statementImports->importAttachmentAndCreateTransactions(
                $workspaceId,
                $user,
                $parsed['media_path'],
                $parsed['original_file_name'] ?? null,
                $parsed['mime'] ?? null,
            );
            if ($statementImport['handled']) {
                $this->reply($phone, $this->statementImports->channelReply($statementImport, 'WhatsApp'));
                if (is_file($parsed['media_path'])) {
                    @unlink($parsed['media_path']);
                }

                return ['handled' => true, 'reply' => 'statement_import'];
            }

            $this->reply($phone, '⏳ Lendo comprovante...');
            $caption = trim((string) ($parsed['caption'] ?? ''));
            $flow = $this->receiptFlow->handleMedia(
                $user,
                $workspaceId,
                'whatsapp',
                $chatId,
                $parsed['message_id'] ?? uniqid('wa_'),
                $parsed['media_path'],
                $parsed['mime'] ?? 'image/jpeg',
                $caption !== '' ? $caption : null,
                $parsed['original_file_name'] ?? null,
            );
            $this->reply($phone, $flow['reply']);
            if (is_file($parsed['media_path'])) {
                @unlink($parsed['media_path']);
            }

            return ['handled' => true, 'reply' => 'receipt_draft'];
        }

        if ($parsed['text'] === '') {
            return ['handled' => false];
        }

        return $this->handleTextLikeTelegram($user, $workspaceId, $chatId, $parsed['text'], $phone);
    }

    /**
     * @return array{handled: bool, reply?: string}
     */
    protected function handleTextLikeTelegram(User $user, int $workspaceId, string $chatId, string $text, string $phone): array
    {
        if ($this->receiptConfirmation->findPendingDraft($user, 'whatsapp', $chatId)) {
            $this->reply($phone, 'Você tem um comprovante aguardando confirmação. Responda SIM ou NÃO.');

            return ['handled' => true, 'reply' => 'pending_receipt'];
        }

        $intent = $this->parser->parse($text);
        if (! $intent) {
            $this->reply($phone, "Envie foto de comprovante ou texto: Gasto de 100 descrição.");

            return ['handled' => true, 'reply' => 'unparsed'];
        }

        $date = now()->toDateString();
        $messageId = uniqid('wa_');
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
            $this->reply($phone, 'ℹ️ Esse lançamento já existe na base. Nada foi duplicado.');

            return ['handled' => true, 'reply' => 'duplicate'];
        }

        $transaction = $this->createTransaction->execute(new CreateTransactionData(
            workspaceId: $workspaceId,
            type: $intent['type'],
            amount: $intent['amount'],
            description: $intent['description'],
            transactionDate: $date,
            status: TransactionStatus::Pending,
            source: 'whatsapp',
            metadata: [
                'whatsapp_phone' => $phone,
                'whatsapp_message_id' => $messageId,
                'fingerprint' => $fingerprint,
                'raw' => $text,
            ],
        ));

        $label = $intent['type'] === TransactionType::Income ? 'Receita' : 'Gasto';
        $amountFormatted = 'R$ '.number_format($intent['amount'], 2, ',', '.');

        $this->reply(
            $phone,
            "✅ {$label} registrado (#{$transaction->id})\nValor: {$amountFormatted}\nDescrição: {$intent['description']}"
        );

        return ['handled' => true, 'reply' => 'created'];
    }

    protected function reply(string $phone, string $text): void
    {
        $this->whatsapp->send($phone, $text);
    }

    protected function resolveUserByPhone(string $phone): ?User
    {
        return User::query()
            ->get()
            ->first(function (User $user) use ($phone) {
                $stored = $user->preferences['notifications']['whatsapp_phone'] ?? null;

                return NotificationDestinationNormalizer::whatsapp((string) $stored) === $phone;
            });
    }

    /**
     * @return array{phone: string, text: string, caption: string, from_me: bool, message_id: ?string, media_path: ?string, mime: ?string, original_file_name?: ?string}|null
     */
    protected function parseEvolutionMessage(array $payload): ?array
    {
        $data = $payload['data'] ?? $payload;
        if (! is_array($data)) {
            return null;
        }

        $key = $data['key'] ?? [];
        $message = $data['message'] ?? [];
        if (! is_array($key) || ! is_array($message)) {
            return null;
        }

        if ($this->shouldSkipFromMeMessage($key, $message)) {
            return null;
        }

        if (str_contains((string) ($key['remoteJid'] ?? ''), '@g.us')) {
            return null;
        }

        $phone = $this->phoneResolver->resolve($key, $data, $payload);
        if ($phone === null) {
            Log::info('WhatsApp inbound: remetente não identificado (JID @lid sem número)', [
                'remoteJid' => $key['remoteJid'] ?? null,
                'remoteJidAlt' => $key['remoteJidAlt'] ?? null,
            ]);

            return null;
        }

        $text = (string) (
            $message['conversation']
            ?? $message['extendedTextMessage']['text']
            ?? data_get($message, 'buttonsResponseMessage.selectedDisplayText')
            ?? ''
        );

        $caption = (string) (
            data_get($message, 'imageMessage.caption')
            ?? data_get($message, 'documentMessage.caption')
            ?? ''
        );
        $originalFileName = (string) (data_get($message, 'documentMessage.fileName') ?? '');

        $mediaPath = null;
        $mime = 'image/jpeg';

        $base64 = $data['base64'] ?? $message['base64'] ?? data_get($data, 'message.base64') ?? null;
        if (is_string($base64) && $base64 !== '') {
            $mimeFromDoc = (string) data_get($message, 'documentMessage.mimetype', '');
            $mime = $mimeFromDoc ?: $this->mimeFromFilename($originalFileName) ?: 'image/jpeg';
            $ext = $this->extensionForMime($mime, $originalFileName);
            $mediaPath = $this->saveBase64Media($base64, $ext);
        } elseif (isset($message['imageMessage']) || isset($message['documentMessage'])) {
            $docMime = (string) data_get($message, 'documentMessage.mimetype', '');
            if ($docMime !== '') {
                $mime = $docMime;
            } elseif ($originalFileName !== '') {
                $mime = $this->mimeFromFilename($originalFileName) ?: $mime;
            }
            $mediaPath = $this->tryDownloadEvolutionMedia($data);
            if ($mediaPath !== null) {
                $mime = $this->mimeFromLocalPath($mediaPath, $mime);
            }
        }

        $docMime = (string) data_get($message, 'documentMessage.mimetype', '');
        $hasReceiptMedia = isset($message['imageMessage']) || isset($message['documentMessage']);

        return [
            'phone' => $phone,
            'text' => trim($text),
            'caption' => trim($caption),
            'from_me' => false,
            'message_id' => (string) ($key['id'] ?? ''),
            'media_path' => $mediaPath,
            'mime' => $mime,
            'original_file_name' => $originalFileName !== '' ? $originalFileName : null,
            'inbound_media_missing' => $hasReceiptMedia && $mediaPath === null,
        ];
    }

    protected function saveBase64Media(string $base64, string $ext): string
    {
        $binary = base64_decode($base64, true) ?: base64_decode($base64);
        $tmp = sys_get_temp_dir().'/wa_'.Str::uuid().'.'.$ext;
        file_put_contents($tmp, $binary);

        return $tmp;
    }

    protected function extensionForMime(string $mime, ?string $fileName = null): string
    {
        $name = strtolower((string) $fileName);
        return match (true) {
            str_ends_with($name, '.ofx') || str_contains($mime, 'ofx') => 'ofx',
            str_ends_with($name, '.csv') || str_contains($mime, 'csv') => 'csv',
            str_ends_with($name, '.txt') || str_contains($mime, 'text/plain') => 'txt',
            str_ends_with($name, '.xml') || str_contains($mime, 'xml') => 'xml',
            str_ends_with($name, '.pdf') || str_contains($mime, 'pdf') => 'pdf',
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'webp') => 'webp',
            default => 'jpg',
        };
    }

    protected function mimeFromFilename(string $fileName): ?string
    {
        $name = strtolower($fileName);

        return match (true) {
            str_ends_with($name, '.ofx') => 'application/x-ofx',
            str_ends_with($name, '.csv') => 'text/csv',
            str_ends_with($name, '.txt') => 'text/plain',
            str_ends_with($name, '.xml') => 'application/xml',
            str_ends_with($name, '.pdf') => 'application/pdf',
            str_ends_with($name, '.png') => 'image/png',
            str_ends_with($name, '.webp') => 'image/webp',
            str_ends_with($name, '.jpg'), str_ends_with($name, '.jpeg') => 'image/jpeg',
            default => null,
        };
    }

    protected function mimeFromLocalPath(string $path, string $fallback): string
    {
        $lower = strtolower($path);

        return match (true) {
            str_ends_with($lower, '.ofx') => 'application/x-ofx',
            str_ends_with($lower, '.csv') => 'text/csv',
            str_ends_with($lower, '.txt') => 'text/plain',
            str_ends_with($lower, '.xml') => 'application/xml',
            str_ends_with($lower, '.pdf') => 'application/pdf',
            str_ends_with($lower, '.png') => 'image/png',
            str_ends_with($lower, '.webp') => 'image/webp',
            default => $fallback,
        };
    }

    /**
     * WhatsApp Business envia comprovantes do cliente com fromMe=true e JID @lid.
     */
    protected function shouldSkipFromMeMessage(array $key, array $message): bool
    {
        if (empty($key['fromMe'])) {
            return false;
        }

        $remoteJid = (string) ($key['remoteJid'] ?? '');
        if (! str_contains(strtolower($remoteJid), '@lid')) {
            return true;
        }

        if (isset($message['imageMessage']) || isset($message['documentMessage'])) {
            return false;
        }

        $text = trim((string) (
            $message['conversation']
            ?? $message['extendedTextMessage']['text']
            ?? ''
        ));

        if ($text === '' || $this->isLikelyBotEcho($text)) {
            return true;
        }

        return false;
    }

    protected function isLikelyBotEcho(string $text): bool
    {
        return (bool) preg_match(
            '/^(📄|⏳|✅|ℹ️|Recebi o CSV|Conta não vinculada|Não consegui baixar|Envie foto|Sua conta não tem workspace)/u',
            $text,
        );
    }

    protected function tryDownloadEvolutionMedia(array $data): ?string
    {
        try {
            $evolution = app(EvolutionService::class);
            if (method_exists($evolution, 'downloadMessageMedia')) {
                return $evolution->downloadMessageMedia($data);
            }
        } catch (\Throwable $e) {
            Log::info('WhatsApp media download skipped', ['message' => $e->getMessage()]);
        }

        return null;
    }
}
