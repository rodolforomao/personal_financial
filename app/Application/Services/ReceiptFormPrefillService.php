<?php

namespace App\Application\Services;

use App\Core\Enums\FundingSource;
use App\Core\Enums\PaymentMethod;
use App\Core\Enums\TransactionType;
use Modules\Integrations\Application\Services\ReceiptExtractionService;

class ReceiptFormPrefillService
{
    public function __construct(
        protected ReceiptExtractionService $extraction,
        protected ReceiptCategorySuggestionService $categorySuggestions,
    ) {}

    /**
     * @return array{form: array<string, mixed>, raw: array<string, mixed>}
     */
    public function extractFromUpload(string $filePath, string $mimeType, int $workspaceId, ?string $originalFileName = null): array
    {
        $raw = $this->extraction->extractFromFile($filePath, $mimeType, $workspaceId, $originalFileName);

        return [
            'form' => $this->mapForForm($raw, $workspaceId),
            'raw' => $raw,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mapForForm(array $extracted, int $workspaceId): array
    {
        $type = TransactionType::tryFrom($extracted['type'] ?? '') ?? TransactionType::Expense;
        $slug = $extracted['suggested_category'] ?? null;
        $description = trim((string) ($extracted['description'] ?? ''));
        $counterparty = trim((string) ($extracted['counterparty'] ?? ''));

        $categorySuggestion = $this->categorySuggestions->forTransaction(
            $workspaceId,
            $description,
            $counterparty !== '' ? $counterparty : null,
            $type,
            $slug,
        );

        $categoryId = $categorySuggestion['recommended']['category_id'] ?? null;

        $funding = FundingSource::tryFromBankSlug($extracted['bank_slug'] ?? null)
            ?? FundingSource::tryFromBankName($extracted['bank'] ?? null);
        $payment = PaymentMethod::tryFromReceiptType($extracted['receipt_type'] ?? null);

        return [
            'type' => $type->value,
            'amount' => (float) ($extracted['amount'] ?? 0),
            'transaction_date' => $extracted['date'] ?? now()->toDateString(),
            'description' => $description,
            'counterparty' => $counterparty,
            'category_id' => $categoryId,
            'category_suggestion' => $categorySuggestion,
            'funding_source' => $funding?->value,
            'payment_method' => $payment?->value,
            'suggested_category_slug' => $slug,
            'bank' => $extracted['bank'] ?? null,
            'confidence' => (float) ($extracted['confidence'] ?? 0),
        ];
    }
}
