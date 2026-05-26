<?php

namespace Tests\Unit;

use App\Core\Enums\TransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Categorization\Application\Services\BulkCategorizeTransactionsService;
use Modules\Categorization\Application\Services\CategorizationService;
use Modules\Categorization\Application\Services\SharedCategorizationRuleSuggestionService;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Categorization\Infrastructure\Models\CategorizationRule;
use Modules\Categorization\Infrastructure\Models\CategorizationRuleAssignment;
use Modules\Core\Infrastructure\Models\Workspace;
use Modules\Finance\Infrastructure\Models\Transaction;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class CategorizationRuleBulkStoreTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
    }

    public function test_store_creates_one_rule_per_name_with_same_category(): void
    {
        $category = Category::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Transporte',
            'slug' => 'transporte',
            'type' => 'expense',
            'is_system' => true,
        ]);

        $controller = app(\App\Http\Controllers\Web\CategorizationRuleController::class);
        $request = \Illuminate\Http\Request::create('/categorization-rules', 'POST', [
            'names' => ['Uber', '99', 'Cabify'],
            'match_type' => 'contains',
            'category_id' => $category->id,
            'transaction_type' => 'expense',
            'priority' => 50,
            'is_active' => '1',
        ]);
        $request->attributes->set('workspace_id', $this->workspace->id);

        $controller->store($request, app(BulkCategorizeTransactionsService::class));

        $this->assertSame(3, CategorizationRule::query()->where('workspace_id', $this->workspace->id)->count());

        $uber = CategorizationRule::query()->where('pattern', 'uber')->first();
        $this->assertNotNull($uber);
        $this->assertSame($category->id, $uber->category_id);
        $this->assertSame('Uber', $uber->name);
        $this->assertSame('expense', $uber->transaction_type);
    }

    public function test_store_applies_new_rule_to_existing_uncategorized_transactions(): void
    {
        $category = Category::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Eventos',
            'slug' => 'eventos',
            'type' => 'expense',
            'is_system' => false,
        ]);

        $transaction = Transaction::query()->create([
            'workspace_id' => $this->workspace->id,
            'type' => TransactionType::Expense,
            'amount' => 550,
            'description' => 'Festivalmicare2026',
            'transaction_date' => '2026-05-03',
            'source' => 'ofx',
        ]);

        $controller = app(\App\Http\Controllers\Web\CategorizationRuleController::class);
        $request = \Illuminate\Http\Request::create('/categorization-rules', 'POST', [
            'names' => ['festival'],
            'match_type' => 'contains',
            'category_id' => $category->id,
            'transaction_type' => 'expense',
            'priority' => 50,
            'is_active' => '1',
        ]);
        $request->attributes->set('workspace_id', $this->workspace->id);

        $controller->store($request, app(BulkCategorizeTransactionsService::class));

        $fresh = $transaction->fresh();
        $this->assertSame($category->id, $fresh->category_id);
        $this->assertSame('95.00', (string) $fresh->categorization_confidence);
        $this->assertSame(1, CategorizationRule::query()->where('pattern', 'festival')->value('hit_count'));
    }

    public function test_contains_rule_ignores_case_and_accents(): void
    {
        $category = Category::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Contas recorrentes',
            'slug' => 'contas-recorrentes',
            'type' => 'expense',
            'is_system' => false,
        ]);

        CategorizationRule::query()->create([
            'workspace_id' => $this->workspace->id,
            'category_id' => $category->id,
            'name' => 'Débito automático',
            'pattern' => 'débito automático',
            'match_type' => 'contains',
            'transaction_type' => 'expense',
            'priority' => 50,
            'is_active' => true,
        ]);

        $suggestion = app(CategorizationService::class)->suggest(
            $this->workspace->id,
            'DEBITO AUTOMATICO NETFLIX',
            null,
            TransactionType::Expense,
        );

        $this->assertSame($category->id, $suggestion['category_id'] ?? null);
    }

    public function test_workspace_can_reuse_shared_rule_without_creating_duplicate_rule(): void
    {
        $sourceWorkspace = Workspace::query()->create([
            'name' => 'Shared Rules',
            'slug' => 'shared-rules-'.uniqid(),
            'currency' => 'BRL',
        ]);
        $sourceCategory = Category::query()->create([
            'workspace_id' => $sourceWorkspace->id,
            'name' => 'Eventos',
            'slug' => 'eventos',
            'type' => 'expense',
            'is_system' => false,
        ]);
        $targetCategory = Category::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Eventos',
            'slug' => 'eventos',
            'type' => 'expense',
            'is_system' => false,
        ]);
        $sharedRule = CategorizationRule::query()->create([
            'workspace_id' => $sourceWorkspace->id,
            'category_id' => $sourceCategory->id,
            'name' => 'Festival',
            'pattern' => 'festival',
            'match_type' => 'contains',
            'transaction_type' => 'expense',
            'priority' => 20,
            'is_active' => true,
        ]);
        $transaction = Transaction::query()->create([
            'workspace_id' => $this->workspace->id,
            'type' => TransactionType::Expense,
            'amount' => 550,
            'description' => 'Festivalmicare2026',
            'transaction_date' => '2026-05-03',
            'source' => 'ofx',
        ]);

        $suggestions = app(SharedCategorizationRuleSuggestionService::class)->forWorkspace($this->workspace->id);

        $this->assertSame($sharedRule->id, $suggestions->first()['rule']->id);
        $this->assertSame($targetCategory->id, $suggestions->first()['suggested_category']->id);

        $controller = app(\App\Http\Controllers\Web\CategorizationRuleController::class);
        $request = \Illuminate\Http\Request::create(
            "/categorization-rules/shared/{$sharedRule->id}/accept",
            'POST',
            ['category_id' => $targetCategory->id]
        );
        $request->attributes->set('workspace_id', $this->workspace->id);

        $controller->acceptShared($request, $sharedRule, app(BulkCategorizeTransactionsService::class));

        $this->assertSame($targetCategory->id, $transaction->fresh()->category_id);
        $this->assertSame(1, CategorizationRuleAssignment::query()
            ->where('workspace_id', $this->workspace->id)
            ->where('categorization_rule_id', $sharedRule->id)
            ->count());
        $this->assertFalse(CategorizationRule::query()
            ->where('workspace_id', $this->workspace->id)
            ->where('pattern', 'festival')
            ->exists());
    }
}
