<?php

namespace Modules\Categorization\Application\Services;

use App\Core\Enums\TransactionType;
use App\Core\Support\FeatureFlag;
use Illuminate\Support\Str;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Categorization\Infrastructure\Models\CategorizationRule;
use Modules\Intelligence\Application\Services\FinancialIntelligenceService;

class CategorizationService
{
    public function __construct(
        protected ?FinancialIntelligenceService $intelligence = null,
    ) {}

    public function suggest(
        int $workspaceId,
        string $description,
        ?string $counterparty = null,
        ?TransactionType $transactionType = null,
    ): ?array {
        $haystack = Str::lower(trim("{$description} {$counterparty}"));

        $rule = CategorizationRule::query()
            ->where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->first(fn (CategorizationRule $r) => $this->matchesRule($r, $haystack, $transactionType));

        if ($rule) {
            $rule->increment('hit_count');

            return $this->normalize($workspaceId, [
                'category_id' => $rule->category_id,
                'confidence' => 95.0,
                'source' => 'rule',
            ]);
        }

        if (FeatureFlag::enabled('ai_categorization', $workspaceId) && $this->intelligence) {
            $ai = $this->intelligence->suggestCategory($workspaceId, $description, $counterparty);

            return $this->normalize($workspaceId, is_array($ai) ? $ai : null);
        }

        return $this->normalize($workspaceId, $this->defaultPatterns($haystack));
    }

    protected function normalize(int $workspaceId, ?array $suggestion): ?array
    {
        if (! $suggestion) {
            return null;
        }

        if (! empty($suggestion['category_id'])) {
            return $suggestion;
        }

        $slug = $suggestion['category_slug'] ?? null;
        if (! $slug) {
            return null;
        }

        $category = Category::query()
            ->where('workspace_id', $workspaceId)
            ->where('slug', $slug)
            ->first();

        if (! $category) {
            return null;
        }

        return [
            'category_id' => $category->id,
            'confidence' => (float) ($suggestion['confidence'] ?? 80),
            'source' => $suggestion['source'] ?? 'pattern',
        ];
    }

    protected function matchesRule(CategorizationRule $rule, string $haystack, ?TransactionType $transactionType): bool
    {
        if ($rule->transaction_type !== null && $transactionType !== null) {
            if ($rule->transaction_type !== $transactionType->value) {
                return false;
            }
        }

        return $this->matchesPattern($rule, $haystack);
    }

    protected function matchesPattern(CategorizationRule $rule, string $haystack): bool
    {
        $pattern = Str::lower($rule->pattern);

        return match ($rule->match_type) {
            'equals' => $haystack === $pattern,
            'starts_with' => str_starts_with($haystack, $pattern),
            'regex' => (bool) @preg_match("/{$pattern}/i", $haystack),
            default => str_contains($haystack, $pattern),
        };
    }

    protected function defaultPatterns(string $haystack): ?array
    {
        $patterns = config('financial.default_categorization_patterns', []);

        foreach ($patterns as $pattern => $slug) {
            if (str_contains($haystack, Str::lower($pattern))) {
                return [
                    'category_slug' => $slug,
                    'confidence' => 80.0,
                    'source' => 'default_pattern',
                ];
            }
        }

        return null;
    }
}
