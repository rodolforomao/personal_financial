<?php

namespace Modules\Integrations\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Integrations\Infrastructure\Models\InboundReceiptDraft;

class InboundReceiptFlowService
{
    public function __construct(
        protected ReceiptExtractionService $extraction,
        protected ReceiptConfirmationService $confirmation,
    ) {}

    /**
     * @return array{handled: bool, reply?: string}
     */
    public function handleMedia(
        User $user,
        int $workspaceId,
        string $channel,
        string $chatId,
        string $messageId,
        string $localPath,
        string $mimeType,
        ?string $userCaption = null,
        ?string $originalFileName = null,
        bool $aiEnabled = true,
    ): array {
        InboundReceiptDraft::query()
            ->where('user_id', $user->id)
            ->where('channel', $channel)
            ->where('chat_id', $chatId)
            ->where('status', InboundReceiptDraft::STATUS_PENDING)
            ->update(['status' => InboundReceiptDraft::STATUS_CANCELLED]);

        try {
            $extracted = $this->extraction->extractFromFile($localPath, $mimeType, $workspaceId, $originalFileName, $aiEnabled);
            if ($userCaption !== null && trim($userCaption) !== '') {
                $extracted = $this->extraction->applyUserCaption($extracted, $userCaption, $workspaceId);
            }

            $stored = $this->storeReceiptCopy($workspaceId, $localPath, $mimeType);

            $draft = InboundReceiptDraft::query()->create([
                'user_id' => $user->id,
                'workspace_id' => $workspaceId,
                'channel' => $channel,
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'status' => InboundReceiptDraft::STATUS_PENDING,
                'extracted' => $extracted,
                'storage_path' => $stored,
                'mime_type' => $mimeType,
                'expires_at' => now()->addHours(24),
            ]);

            return [
                'handled' => true,
                'reply' => $this->confirmation->formatDraftForConfirmation($draft),
            ];
        } catch (\Throwable $e) {
            Log::warning('Receipt inbound failed', ['message' => $e->getMessage()]);

            return [
                'handled' => true,
                'reply' => 'Não consegui ler o arquivo. Envie foto nítida (JPEG/PNG), PDF/XML de nota fiscal ou texto: Receita de 356 recebimento mensal telecom.',
            ];
        }
    }

    /**
     * @return array{handled: bool, reply?: string}|null
     */
    public function handleConfirmationText(User $user, string $channel, string $chatId, string $text): ?array
    {
        $draft = $this->confirmation->findPendingDraft($user, $channel, $chatId);
        if (! $draft) {
            return null;
        }

        if ($this->confirmation->isConfirmationNo($text)) {
            $this->confirmation->cancelDraft($draft);

            return ['handled' => true, 'reply' => 'Comprovante descartado. Envie outro quando quiser.'];
        }

        if ($this->confirmation->isConfirmationYes($text)) {
            $result = $this->confirmation->confirmDraft($draft);

            return ['handled' => true, 'reply' => $result['message']];
        }

        if ($this->applySupplementText($draft, $text)) {
            return [
                'handled' => true,
                'reply' => "✏️ Atualizei com seus dados.\n\n".$this->confirmation->formatDraftForConfirmation($draft->fresh()),
            ];
        }

        return [
            'handled' => true,
            'reply' => "Aguardando confirmação.\n\n".$this->confirmation->formatDraftForConfirmation($draft),
        ];
    }

    /**
     * Atualiza rascunho quando o usuário envia legenda em mensagem separada (após a foto).
     */
    public function applySupplementText(InboundReceiptDraft $draft, string $text): bool
    {
        $trim = trim($text);
        if ($trim === ''
            || $this->confirmation->isConfirmationYes($trim)
            || $this->confirmation->isConfirmationNo($trim)) {
            return false;
        }

        $result = $this->extraction->applyDraftSupplement($draft->extracted ?? [], $trim, (int) $draft->workspace_id);
        if (! $result['changed']) {
            return false;
        }

        $draft->update(['extracted' => $result['extracted']]);

        return true;
    }

    protected function storeReceiptCopy(int $workspaceId, string $localPath, string $mimeType): string
    {
        $ext = match (true) {
            str_contains($mimeType, 'xml') => 'xml',
            str_contains($mimeType, 'png') => 'png',
            str_contains($mimeType, 'pdf') => 'pdf',
            default => 'jpg',
        };
        $relative = "receipts/{$workspaceId}/".Str::uuid().".{$ext}";
        Storage::disk('local')->put($relative, file_get_contents($localPath) ?: '');

        return $relative;
    }
}
