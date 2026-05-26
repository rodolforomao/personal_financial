<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Categorization\Application\Services\BulkCategorizeTransactionsService;
use Modules\Categorization\Application\Services\SharedCategorizationRuleSuggestionService;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Categorization\Infrastructure\Models\CategorizationRule;
use Modules\Categorization\Infrastructure\Models\CategorizationRuleAssignment;

class CategorizationRuleController extends Controller
{
    public function index(Request $request, SharedCategorizationRuleSuggestionService $sharedSuggestions): View
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');

        $rules = CategorizationRule::query()
            ->where('workspace_id', $workspaceId)
            ->with('category')
            ->orderBy('priority')
            ->orderBy('name')
            ->orderBy('pattern')
            ->get();

        $categories = Category::query()
            ->where('workspace_id', $workspaceId)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $suggestedNames = $rules
            ->pluck('name')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return view('categorization-rules.index', [
            'rules' => $rules,
            'sharedAssignments' => CategorizationRuleAssignment::query()
                ->where('workspace_id', $workspaceId)
                ->with(['rule.category', 'category'])
                ->orderBy('priority')
                ->orderBy('id')
                ->get(),
            'sharedSuggestions' => $sharedSuggestions->forWorkspace($workspaceId),
            'categories' => $categories,
            'matchTypes' => $this->matchTypes(),
            'suggestedNames' => $suggestedNames,
        ]);
    }

    public function store(Request $request, BulkCategorizeTransactionsService $bulkCategorize): RedirectResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $validated = $this->validateBulkCreate($request);

        $this->assertCategoryInWorkspace($workspaceId, (int) $validated['category_id']);

        $created = 0;
        $updated = 0;
        $ruleIds = [];

        foreach ($validated['names'] as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            $pattern = Str::lower($name);

            $rule = CategorizationRule::query()->updateOrCreate(
                [
                    'workspace_id' => $workspaceId,
                    'pattern' => $pattern,
                ],
                [
                    'category_id' => $validated['category_id'],
                    'name' => $name,
                    'match_type' => $validated['match_type'],
                    'transaction_type' => $validated['transaction_type'] ?? null,
                    'priority' => $validated['priority'],
                    'is_active' => $request->boolean('is_active', true),
                ],
            );

            if ($rule->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }

            $ruleIds[] = $rule->id;
        }

        $total = $created + $updated;
        if ($total === 0) {
            return back()->with('warning', 'Informe ao menos um nome.');
        }

        $message = $created > 0
            ? "{$created} regra(s) criada(s)."
            : '';
        if ($updated > 0) {
            $message .= ($message ? ' ' : '')."{$updated} regra(s) atualizada(s) (padrão já existia).";
        }

        if ($request->boolean('is_active', true)) {
            $result = $bulkCategorize->runForRules($workspaceId, $ruleIds);
            if ($result['categorized'] > 0) {
                $message .= ($message ? ' ' : '')."{$result['categorized']} transação(ões) sem categoria atualizada(s).";
            }
        }

        return back()->with('success', trim($message));
    }

    public function update(
        Request $request,
        CategorizationRule $categorizationRule,
        BulkCategorizeTransactionsService $bulkCategorize,
    ): RedirectResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $this->assertRuleInWorkspace($categorizationRule, $workspaceId);

        $validated = $this->validateRule($request);
        $this->assertCategoryInWorkspace($workspaceId, (int) $validated['category_id']);

        $categorizationRule->update([
            ...$validated,
            'is_active' => $request->boolean('is_active'),
        ]);

        $message = 'Regra atualizada.';
        if ($categorizationRule->is_active) {
            $result = $bulkCategorize->runForRules($workspaceId, [$categorizationRule->id]);
            if ($result['categorized'] > 0) {
                $message .= " {$result['categorized']} transação(ões) sem categoria atualizada(s).";
            }
        }

        return back()->with('success', $message);
    }

    public function destroy(Request $request, CategorizationRule $categorizationRule): RedirectResponse
    {
        $this->assertRuleInWorkspace($categorizationRule, (int) $request->attributes->get('workspace_id'));
        $categorizationRule->delete();

        return back()->with('success', 'Regra removida.');
    }

    public function toggle(
        Request $request,
        CategorizationRule $categorizationRule,
        BulkCategorizeTransactionsService $bulkCategorize,
    ): RedirectResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $this->assertRuleInWorkspace($categorizationRule, $workspaceId);
        $categorizationRule->update(['is_active' => ! $categorizationRule->is_active]);

        $message = $categorizationRule->is_active ? 'Regra ativada.' : 'Regra desativada.';
        if ($categorizationRule->is_active) {
            $result = $bulkCategorize->runForRules($workspaceId, [$categorizationRule->id]);
            if ($result['categorized'] > 0) {
                $message .= " {$result['categorized']} transação(ões) sem categoria atualizada(s).";
            }
        }

        return back()->with('success', $message);
    }

    public function acceptShared(
        Request $request,
        CategorizationRule $categorizationRule,
        BulkCategorizeTransactionsService $bulkCategorize,
    ): RedirectResponse {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        abort_if($categorizationRule->workspace_id === $workspaceId, 404);

        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'priority' => 'nullable|integer|min:1|max:9999',
        ]);
        $this->assertCategoryInWorkspace($workspaceId, (int) $validated['category_id']);

        if ($this->workspaceHasEquivalentRule($workspaceId, $categorizationRule)) {
            return back()->with('info', 'Este workspace já possui uma regra equivalente.');
        }

        $assignment = CategorizationRuleAssignment::query()->firstOrCreate(
            [
                'workspace_id' => $workspaceId,
                'categorization_rule_id' => $categorizationRule->id,
            ],
            [
                'category_id' => $validated['category_id'],
                'priority' => (int) ($validated['priority'] ?? $categorizationRule->priority),
                'is_active' => true,
            ],
        );

        $result = $bulkCategorize->runForRules($workspaceId, [$categorizationRule->id]);
        $message = $assignment->wasRecentlyCreated
            ? 'Regra compartilhada vinculada.'
            : 'Regra compartilhada já estava vinculada.';

        if ($result['categorized'] > 0) {
            $message .= " {$result['categorized']} transação(ões) sem categoria atualizada(s).";
        }

        return back()->with('success', $message);
    }

    /**
     * @return array<string, string>
     */
    protected function matchTypes(): array
    {
        return [
            'contains' => 'Contém o texto',
            'starts_with' => 'Começa com',
            'equals' => 'Igual a',
            'regex' => 'Expressão regular',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateBulkCreate(Request $request): array
    {
        $validated = $request->validate([
            'names' => 'required|array|min:1',
            'names.*' => 'required|string|max:120',
            'match_type' => 'required|in:contains,starts_with,equals,regex',
            'category_id' => 'required|exists:categories,id',
            'transaction_type' => 'nullable|in:income,expense',
            'priority' => 'required|integer|min:1|max:9999',
        ]);

        $validated['names'] = collect($validated['names'])
            ->map(fn (string $n) => trim($n))
            ->filter()
            ->unique(fn (string $n) => Str::lower($n))
            ->values()
            ->all();

        if ($validated['names'] === []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'names' => 'Informe ao menos um nome.',
            ]);
        }

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateRule(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'pattern' => 'required|string|max:255',
            'match_type' => 'required|in:contains,starts_with,equals,regex',
            'category_id' => 'required|exists:categories,id',
            'transaction_type' => 'nullable|in:income,expense',
            'priority' => 'required|integer|min:1|max:9999',
        ]);
    }

    protected function assertRuleInWorkspace(CategorizationRule $rule, int $workspaceId): void
    {
        abort_unless($rule->workspace_id === $workspaceId, 404);
    }

    protected function assertCategoryInWorkspace(int $workspaceId, int $categoryId): void
    {
        abort_unless(
            Category::query()->where('workspace_id', $workspaceId)->whereKey($categoryId)->exists(),
            422,
            'Categoria inválida para este workspace.'
        );
    }

    protected function workspaceHasEquivalentRule(int $workspaceId, CategorizationRule $sharedRule): bool
    {
        return CategorizationRule::query()
            ->where('workspace_id', $workspaceId)
            ->where('match_type', $sharedRule->match_type)
            ->where('pattern', $sharedRule->pattern)
            ->where('transaction_type', $sharedRule->transaction_type)
            ->exists();
    }
}
