<?php

namespace Modules\Categorization\Application\Services;

use App\Core\Enums\TransactionType;
use App\Core\Support\FeatureFlag;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Categorization\Infrastructure\Models\CategorizationRule;
use Modules\Categorization\Infrastructure\Models\CategorizationRuleAssignment;
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
        $haystack = $this->normalizeText(trim("{$description} {$counterparty}"));

        $ruleSuggestion = $this->suggestFromRules($workspaceId, $description, $counterparty, $transactionType);
        if ($ruleSuggestion) {
            return $ruleSuggestion;
        }

        if (FeatureFlag::enabled('ai_categorization', $workspaceId) && $this->intelligence) {
            $ai = $this->intelligence->suggestCategory($workspaceId, $description, $counterparty);

            return $this->normalize($workspaceId, is_array($ai) ? $ai : null);
        }

        return $this->normalize($workspaceId, $this->defaultPatterns($haystack));
    }

    /**
     * @param  array<int>|null  $onlyRuleIds
     */
    public function suggestFromRules(
        int $workspaceId,
        string $description,
        ?string $counterparty = null,
        ?TransactionType $transactionType = null,
        ?array $onlyRuleIds = null,
    ): ?array {
        $haystack = $this->normalizeText(trim("{$description} {$counterparty}"));

        $ruleQuery = CategorizationRule::query()
            ->where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id');

        if ($onlyRuleIds !== null) {
            $ruleQuery->whereIn('id', $onlyRuleIds);
        }

        $candidates = $this->candidateRules($workspaceId, $ruleQuery->get(), $onlyRuleIds);

        foreach ($candidates as $candidate) {
            /** @var CategorizationRule $rule */
            $rule = $candidate['rule'];

            if (! $this->matchesRule($rule, $haystack, $transactionType)) {
                continue;
            }

            if ($candidate['assignment']) {
                $candidate['assignment']->increment('hit_count');
            } else {
                $rule->increment('hit_count');
            }

            return $this->normalize($workspaceId, [
                'category_id' => $candidate['category_id'],
                'confidence' => 95.0,
                'source' => $candidate['assignment'] ? 'shared_rule' : 'rule',
            ]);
        }

        return null;
    }

    public function ruleMatchesTransaction(
        CategorizationRule $rule,
        string $description,
        ?string $counterparty = null,
        ?TransactionType $transactionType = null,
    ): bool {
        $haystack = $this->normalizeText(trim("{$description} {$counterparty}"));

        return $this->matchesRule($rule, $haystack, $transactionType);
    }

    /**
     * @param  Collection<int, CategorizationRule>  $workspaceRules
     * @param  array<int>|null  $onlyRuleIds
     * @return Collection<int, array{rule: CategorizationRule, assignment: ?CategorizationRuleAssignment, category_id: int, priority: int, sort_id: int}>
     */
    protected function candidateRules(int $workspaceId, Collection $workspaceRules, ?array $onlyRuleIds = null): Collection
    {
        $candidates = $workspaceRules->map(fn (CategorizationRule $rule) => [
            'rule' => $rule,
            'assignment' => null,
            'category_id' => $rule->category_id,
            'priority' => (int) $rule->priority,
            'sort_id' => (int) $rule->id,
        ]);

        $assignmentQuery = CategorizationRuleAssignment::query()
            ->where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->whereHas('rule', fn ($query) => $query->where('is_active', true))
            ->with('rule');

        if ($onlyRuleIds !== null) {
            $assignmentQuery->whereIn('categorization_rule_id', $onlyRuleIds);
        }

        $assignmentCandidates = $assignmentQuery->get()->map(fn (CategorizationRuleAssignment $assignment) => [
            'rule' => $assignment->rule,
            'assignment' => $assignment,
            'category_id' => $assignment->category_id,
            'priority' => (int) $assignment->priority,
            'sort_id' => (int) $assignment->id,
        ])->filter(fn (array $candidate) => $candidate['rule'] instanceof CategorizationRule);

        return $candidates
            ->concat($assignmentCandidates)
            ->sortBy([
                ['priority', 'asc'],
                ['sort_id', 'asc'],
            ])
            ->values();
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
        $pattern = $rule->match_type === 'regex'
            ? trim((string) $rule->pattern)
            : $this->normalizeText($rule->pattern);

        return match ($rule->match_type) {
            'equals' => $haystack === $pattern,
            'starts_with' => str_starts_with($haystack, $pattern),
            'regex' => (bool) @preg_match("/{$pattern}/iu", $haystack),
            default => str_contains($haystack, $pattern),
        };
    }

    protected function defaultPatterns(string $haystack): ?array
    {
        $patterns = (array) config('financial.default_categorization_patterns', []);

        foreach ($patterns as $pattern => $slug) {
            if (str_contains($haystack, $this->normalizeText($pattern))) {
                return [
                    'category_slug' => $slug,
                    'confidence' => 80.0,
                    'source' => 'default_pattern',
                ];
            }
        }

        return null;
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
