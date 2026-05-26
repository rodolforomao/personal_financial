<?php

namespace Modules\Categorization\Application\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Categorization\Infrastructure\Models\CategorizationRule;
use Modules\Categorization\Infrastructure\Models\CategorizationRuleAssignment;
use Modules\Finance\Infrastructure\Models\Transaction;

class SharedCategorizationRuleSuggestionService
{
    public function __construct(
        protected CategorizationService $categorization,
    ) {}

    /**
     * @return Collection<int, array{rule: CategorizationRule, matches_count: int, sample_description: string, suggested_category: ?Category}>
     */
    public function forWorkspace(int $workspaceId, int $limit = 8): Collection
    {
        $transactions = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->whereNull('category_id')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(250)
            ->get();

        if ($transactions->isEmpty()) {
            return collect();
        }

        $knownKeys = $this->knownRuleKeys($workspaceId);
        $linkedRuleIds = CategorizationRuleAssignment::query()
            ->where('workspace_id', $workspaceId)
            ->pluck('categorization_rule_id')
            ->all();

        return CategorizationRule::query()
            ->where('workspace_id', '!=', $workspaceId)
            ->where('is_active', true)
            ->whereNotIn('id', $linkedRuleIds)
            ->with('category')
            ->orderByDesc('hit_count')
            ->orderBy('priority')
            ->limit(500)
            ->get()
            ->reject(fn (CategorizationRule $rule) => $knownKeys->contains($this->ruleKey($rule)))
            ->map(function (CategorizationRule $rule) use ($workspaceId, $transactions) {
                $matches = $transactions->filter(fn (Transaction $transaction) => $this->categorization->ruleMatchesTransaction(
                    $rule,
                    $transaction->description,
                    $transaction->counterparty,
                    $transaction->type,
                ));

                if ($matches->isEmpty()) {
                    return null;
                }

                return [
                    'rule' => $rule,
                    'matches_count' => $matches->count(),
                    'sample_description' => (string) $matches->first()->description,
                    'suggested_category' => $this->matchingCategory($workspaceId, $rule->category),
                ];
            })
            ->filter()
            ->sortByDesc('matches_count')
            ->take($limit)
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    protected function knownRuleKeys(int $workspaceId): Collection
    {
        $ownKeys = CategorizationRule::query()
            ->where('workspace_id', $workspaceId)
            ->get()
            ->map(fn (CategorizationRule $rule) => $this->ruleKey($rule));

        $assignedKeys = CategorizationRuleAssignment::query()
            ->where('workspace_id', $workspaceId)
            ->with('rule')
            ->get()
            ->map(fn (CategorizationRuleAssignment $assignment) => $assignment->rule ? $this->ruleKey($assignment->rule) : null)
            ->filter();

        return $ownKeys->concat($assignedKeys)->unique()->values();
    }

    protected function matchingCategory(int $workspaceId, ?Category $sourceCategory): ?Category
    {
        if (! $sourceCategory) {
            return null;
        }

        $query = Category::query()
            ->where('workspace_id', $workspaceId)
            ->where('type', $sourceCategory->type);

        return (clone $query)
            ->where('slug', $sourceCategory->slug)
            ->first()
            ?? $query->where('name', $sourceCategory->name)->first();
    }

    protected function ruleKey(CategorizationRule $rule): string
    {
        return implode('|', [
            $rule->match_type,
            $this->normalizeText($rule->pattern),
            $rule->transaction_type ?? '*',
        ]);
    }

    protected function normalizeText(?string $value): string
    {
        return Str::of((string) $value)
            ->ascii()
            ->lower()
            ->squish()
            ->toString();
    }
}
