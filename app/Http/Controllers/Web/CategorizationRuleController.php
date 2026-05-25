<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Categorization\Infrastructure\Models\CategorizationRule;

class CategorizationRuleController extends Controller
{
    public function index(Request $request): View
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
            'categories' => $categories,
            'matchTypes' => $this->matchTypes(),
            'suggestedNames' => $suggestedNames,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $validated = $this->validateBulkCreate($request);

        $this->assertCategoryInWorkspace($workspaceId, (int) $validated['category_id']);

        $created = 0;
        $updated = 0;

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

        return back()->with('success', trim($message));
    }

    public function update(Request $request, CategorizationRule $categorizationRule): RedirectResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $this->assertRuleInWorkspace($categorizationRule, $workspaceId);

        $validated = $this->validateRule($request);
        $this->assertCategoryInWorkspace($workspaceId, (int) $validated['category_id']);

        $categorizationRule->update([
            ...$validated,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Regra atualizada.');
    }

    public function destroy(Request $request, CategorizationRule $categorizationRule): RedirectResponse
    {
        $this->assertRuleInWorkspace($categorizationRule, (int) $request->attributes->get('workspace_id'));
        $categorizationRule->delete();

        return back()->with('success', 'Regra removida.');
    }

    public function toggle(Request $request, CategorizationRule $categorizationRule): RedirectResponse
    {
        $this->assertRuleInWorkspace($categorizationRule, (int) $request->attributes->get('workspace_id'));
        $categorizationRule->update(['is_active' => ! $categorizationRule->is_active]);

        return back()->with('success', $categorizationRule->is_active ? 'Regra ativada.' : 'Regra desativada.');
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
}
