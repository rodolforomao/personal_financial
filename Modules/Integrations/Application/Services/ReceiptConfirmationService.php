<?php

namespace Modules\Integrations\Application\Services;

use App\Core\Enums\FundingSource;
use App\Core\Enums\PaymentMethod;
use App\Core\Enums\TransactionStatus;
use App\Core\Enums\TransactionType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Modules\Categorization\Application\Services\CategorizationService;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Finance\Application\Actions\CreateTransactionAction;
use Modules\Finance\Application\DTOs\CreateTransactionData;
use Modules\Finance\Application\Services\TransactionDeduplicationService;
use Modules\Integrations\Infrastructure\Models\InboundReceiptDraft;
use Modules\OCR\Application\Services\ReceiptStorageService;

class ReceiptConfirmationService
{
    public function __construct(
        protected CreateTransactionAction $createTransaction,
        protected TransactionDeduplicationService $deduplication,
        protected CategorizationService $categorization,
        protected ReceiptStorageService $receiptStorage,
    ) {}

    public function findPendingDraft(User $user, string $channel, string $chatId): ?InboundReceiptDraft
    {
        return InboundReceiptDraft::query()
            ->where('user_id', $user->id)
            ->where('channel', $channel)
            ->where('chat_id', $chatId)
            ->where('status', InboundReceiptDraft::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }

    public function cancelDraft(InboundReceiptDraft $draft): void
    {
        $draft->update(['status' => InboundReceiptDraft::STATUS_CANCELLED]);
    }

    /**
     * @return array{ok: bool, message: string, transaction_id?: int}
     */
    public function confirmDraft(InboundReceiptDraft $draft): array
    {
        $data = $draft->extracted;
        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            return [
                'ok' => false,
                'message' => 'Não foi possível identificar o valor no comprovante. Envie outra foto mais nítida ou lance por texto.',
            ];
        }

        $type = TransactionType::tryFrom($data['type'] ?? '') ?? TransactionType::Expense;
        $date = $data['date'] ?? now()->toDateString();
        $description = $data['description'] ?? 'Comprovante';
        $counterparty = $data['counterparty'] ?? null;
        $documentType = (string) ($data['document_type'] ?? 'receipt');

        $fingerprint = hash('sha256', implode('|', [
            $draft->channel,
            $draft->message_id ?? $draft->id,
            $type->value,
            (string) $amount,
            $date,
            mb_strtolower($description),
        ]));

        if ($this->deduplication->exists(
            $draft->workspace_id,
            $type,
            $amount,
            $date,
            $description,
            $fingerprint,
        )) {
            $draft->update(['status' => InboundReceiptDraft::STATUS_CANCELLED]);

            return [
                'ok' => false,
                'message' => 'ℹ️ Esse comprovante já foi registrado (valor, data e descrição iguais).',
            ];
        }

        $categoryId = $this->resolveCategoryId($draft->workspace_id, $data);
        $status = config('financial.receipt_caption.confirmed_on_inbound', true)
            ? TransactionStatus::Confirmed
            : TransactionStatus::Pending;

        $transaction = $this->createTransaction->execute(new CreateTransactionData(
            workspaceId: $draft->workspace_id,
            type: $type,
            amount: $amount,
            description: $description,
            transactionDate: $date,
            categoryId: $categoryId,
            companyId: isset($data['company_id']) ? (int) $data['company_id'] : null,
            operationId: isset($data['operation_id']) ? (int) $data['operation_id'] : null,
            operationUnitId: isset($data['operation_unit_id']) ? (int) $data['operation_unit_id'] : null,
            counterparty: $counterparty,
            fundingSource: $this->resolveFundingSource($data),
            paymentMethod: $this->resolvePaymentMethod($data),
            status: $status,
            source: $draft->channel,
            metadata: [
                'inbound_receipt_draft_id' => $draft->id,
                'channel' => $draft->channel,
                'chat_id' => $draft->chat_id,
                'message_id' => $draft->message_id,
                'fingerprint' => $fingerprint,
                'ocr_provider' => $data['ocr_provider'] ?? null,
                'bank' => $data['bank'] ?? null,
            ],
        ));

        $draft->update(['status' => InboundReceiptDraft::STATUS_CONFIRMED]);

        $receiptNote = '';
        if ($draft->storage_path) {
            $user = $draft->user ?? User::query()->find($draft->user_id);
            if ($user) {
                $ocrPayload = array_filter([
                    'amount' => $amount,
                    'date' => $date,
                    'description' => $description,
                    'counterparty' => $counterparty,
                    'bank' => $data['bank'] ?? null,
                    'type' => $type->value,
                    'confidence' => $data['confidence'] ?? null,
                    'ocr_provider' => $data['ocr_provider'] ?? null,
                    'raw_text' => $data['raw_text'] ?? null,
                    'invoice' => $data['invoice'] ?? null,
                ]);
                $doc = $this->receiptStorage->attachFromInboundPath(
                    $draft->workspace_id,
                    $user,
                    $draft->storage_path,
                    $draft->mime_type ?? 'image/jpeg',
                    ($documentType === 'invoice' ? 'nota-fiscal-' : 'comprovante-').
                        $draft->channel.'-'.$draft->id.'.'.($this->extensionFromMime($draft->mime_type ?? '')),
                    $transaction->id,
                    $ocrPayload ?: null,
                    $documentType,
                );
                if ($doc) {
                    $receiptNote = $documentType === 'invoice'
                        ? "\n📎 Nota fiscal anexada à transação."
                        : "\n📎 Comprovante anexado à transação.";
                }
            }
        }

        $label = $type === TransactionType::Income ? 'Receita' : 'Gasto';
        $amountFormatted = 'R$ '.number_format($amount, 2, ',', '.');

        return [
            'ok' => true,
            'message' => "✅ {$label} salvo (#{$transaction->id})\n".
                "Valor: {$amountFormatted}\n".
                "Descrição: {$description}\n".
                'Data: '.Carbon::parse($date)->format('d/m/Y').
                $receiptNote,
            'transaction_id' => $transaction->id,
        ];
    }

    public function formatDraftForConfirmation(InboundReceiptDraft $draft): string
    {
        $d = $draft->extracted;
        $type = ($d['type'] ?? 'expense') === 'income' ? 'Receita' : 'Gasto';
        $amount = (float) ($d['amount'] ?? 0);
        $amountStr = $amount > 0
            ? 'R$ '.number_format($amount, 2, ',', '.')
            : '(não identificado — confirme só se estiver correto no comprovante)';

        $lines = [
            '📄 Li o comprovante. Está correto?',
            '',
            'Comprovante: '.($d['receipt_type_label'] ?? 'Bancário'),
            "Tipo lançamento: {$type}",
            "Valor: {$amountStr}",
            'Data: '.(isset($d['date']) ? Carbon::parse($d['date'])->format('d/m/Y') : '—'),
            'Descrição: '.($d['description'] ?? '—'),
        ];

        if (! empty($d['counterparty'])) {
            $lines[] = 'Contraparte: '.$d['counterparty'];
        }
        if (! empty($d['invoice']['number'])) {
            $lines[] = 'NF-e: '.$d['invoice']['number'];
        }
        if (! empty($d['invoice']['access_key'])) {
            $lines[] = 'Chave NF-e: '.$d['invoice']['access_key'];
        }
        if (! empty($d['bank'])) {
            $lines[] = 'Banco: '.$d['bank'];
        }
        $funding = $this->resolveFundingSource($d);
        $payment = $this->resolvePaymentMethod($d);
        if ($funding) {
            $lines[] = 'Fonte: '.(FundingSource::tryFrom($funding)?->label() ?? $funding);
        }
        if ($payment) {
            $lines[] = 'Meio: '.(PaymentMethod::tryFrom($payment)?->label() ?? $payment);
        }
        if (! empty($d['category_name'])) {
            $lines[] = 'Categoria: '.$d['category_name'];
        } elseif (! empty($d['suggested_category'])) {
            $lines[] = 'Categoria sugerida: '.$d['suggested_category'];
        }
        if (! empty($d['company_name'])) {
            $lines[] = 'Empresa: '.$d['company_name'];
        }
        if (! empty($d['operation_name'])) {
            $lines[] = 'Operação: '.$d['operation_name'];
        }
        if (! empty($d['operation_unit_name'])) {
            $lines[] = 'Unidade: '.$d['operation_unit_name'];
        }
        if (! empty($d['caption_from_user']) || ! empty($d['supplement_from_user'])) {
            $lines[] = '(Legenda/descrição sua aplicada)';
        }
        if (config('financial.receipt_caption.confirmed_on_inbound', true)) {
            $lines[] = 'Status ao salvar: Confirmado';
        }

        if (! empty($d['confidence']) && (float) $d['confidence'] < 0.5) {
            $lines[] = '';
            $lines[] = '⚠️ Leitura com baixa confiança — confira o valor e a data no comprovante.';
        }

        $lines[] = '';
        $lines[] = 'Antes de confirmar, você pode ajustar com texto, por exemplo:';
        $lines[] = 'Contraparte: presente da Maria. Categoria: presentes. Empresa pessoal, operação geral.';
        $lines[] = '';
        $lines[] = 'Responda SIM para salvar ou NÃO para descartar.';
        $lines[] = '(Também aceito: ok, confirmar, salvar, correto / não, cancelar)';

        return implode("\n", $lines);
    }

    protected function extensionFromMime(string $mime): string
    {
        return match (true) {
            str_contains($mime, 'xml') => 'xml',
            str_contains($mime, 'pdf') => 'pdf',
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'webp') => 'webp',
            default => 'jpg',
        };
    }

    public function isConfirmationYes(string $text): bool
    {
        $t = mb_strtolower(trim($text));

        return in_array($t, ['sim', 's', 'yes', 'y', 'ok', 'confirmar', 'confirma', 'positivo', 'salvar', 'correto'], true)
            || str_starts_with($t, 'sim ');
    }

    public function isConfirmationNo(string $text): bool
    {
        $t = mb_strtolower(trim($text));

        return in_array($t, ['nao', 'não', 'n', 'no', 'cancelar', 'cancela', 'descartar', 'negativo'], true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveFundingSource(array $data): ?string
    {
        if (! empty($data['funding_source'])) {
            return (string) $data['funding_source'];
        }

        return FundingSource::tryFromBankSlug($data['bank_slug'] ?? null)?->value
            ?? FundingSource::tryFromBankName($data['bank'] ?? null)?->value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolvePaymentMethod(array $data): ?string
    {
        if (! empty($data['payment_method'])) {
            return (string) $data['payment_method'];
        }

        return PaymentMethod::tryFromReceiptType($data['receipt_type'] ?? null)?->value;
    }

    protected function resolveCategoryId(int $workspaceId, array $data): ?int
    {
        if (! empty($data['category_id'])) {
            return (int) $data['category_id'];
        }

        $slug = $data['suggested_category'] ?? null;
        if ($slug) {
            $id = Category::query()
                ->where('workspace_id', $workspaceId)
                ->where('slug', $slug)
                ->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        $type = isset($data['type']) && $data['type'] instanceof \App\Core\Enums\TransactionType
            ? $data['type']
            : \App\Core\Enums\TransactionType::tryFrom((string) ($data['type'] ?? ''));

        $suggestion = $this->categorization->suggest(
            $workspaceId,
            $data['description'] ?? '',
            $data['counterparty'] ?? null,
            $type,
        );

        return $suggestion['category_id'] ?? null;
    }
}
