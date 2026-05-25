<?php

namespace Modules\Finance\Application\Services;

use App\Core\Enums\FundingSource;
use App\Core\Enums\PaymentMethod;
use Illuminate\Support\Str;
use Modules\Categorization\Application\Services\CategorizationService;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Finance\Infrastructure\Models\StatementImport;
use Modules\Finance\Infrastructure\Models\StatementLine;

class StatementTransactionDefaultsResolver
{
    public function __construct(
        protected CategorizationService $categorization,
    ) {}

    /**
     * @return array{category_id: ?int, funding_source: ?string, payment_method: ?string}
     */
    public function resolve(StatementImport $import, StatementLine $line): array
    {
        $haystack = Str::lower(trim("{$line->description} {$line->counterparty}"));

        $categoryId = $this->resolveCategoryId(
            $import->workspace_id,
            $line->description,
            $line->counterparty,
            $line->type,
        );
        $fundingSource = $this->resolveFundingSource($import, $haystack);
        $paymentMethod = $this->resolvePaymentMethod($haystack);

        return [
            'category_id' => $categoryId,
            'funding_source' => $fundingSource,
            'payment_method' => $paymentMethod,
        ];
    }

    public function detectBankSlugFromOfxContent(string $content): ?string
    {
        $lower = Str::lower($content);

        $bankSignatures = config('financial.statement_import.bank_signatures') ?? [];

        foreach ($bankSignatures as $slug => $signatures) {
            foreach ($signatures as $needle) {
                if (str_contains($lower, Str::lower($needle))) {
                    return $slug;
                }
            }
        }

        return null;
    }

    protected function resolveCategoryId(
        int $workspaceId,
        string $description,
        ?string $counterparty,
        ?\App\Core\Enums\TransactionType $lineType = null,
    ): ?int {
        if ($this->isUber($description, $counterparty)) {
            $transport = Category::query()
                ->where('workspace_id', $workspaceId)
                ->where('slug', 'transporte')
                ->first();

            if ($transport) {
                return $transport->id;
            }
        }

        $suggestion = $this->categorization->suggest(
            $workspaceId,
            $description,
            $counterparty ?? '',
            $lineType ?? null,
        );

        return $suggestion['category_id'] ?? null;
    }

    protected function isUber(string $description, ?string $counterparty): bool
    {
        $haystack = Str::lower(trim("{$description} {$counterparty}"));

        $uberPatterns = config('financial.statement_import.merchant_patterns.uber') ?? ['uber'];

        foreach ($uberPatterns as $pattern) {
            if (str_contains($haystack, Str::lower($pattern))) {
                return true;
            }
        }

        return false;
    }

    protected function resolveFundingSource(StatementImport $import, string $haystack): ?string
    {
        $bankSlug = $import->settings['bank_slug'] ?? null;

        if ($bankSlug) {
            return FundingSource::tryFromBankSlug($bankSlug)?->value;
        }

        return $this->fundingFromHaystack($haystack);
    }

    protected function fundingFromHaystack(string $haystack): ?string
    {
        if (str_contains($haystack, 'banco inter') || preg_match('/\binter\b/', $haystack)) {
            return FundingSource::Inter->value;
        }

        return null;
    }

    protected function resolvePaymentMethod(string $haystack): ?string
    {
        $patterns = config('financial.statement_import.payment_method_patterns') ?? [];

        foreach ($patterns as $pattern => $method) {
            if (str_contains($haystack, Str::lower($pattern))) {
                $resolved = PaymentMethod::tryFrom($method);

                return $resolved?->value;
            }
        }

        return null;
    }
}
