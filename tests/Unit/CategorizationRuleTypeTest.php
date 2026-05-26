<?php

namespace Tests\Unit;

use App\Core\Enums\TransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Categorization\Application\Services\CategorizationService;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Categorization\Infrastructure\Models\CategorizationRule;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class CategorizationRuleTypeTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
    }

    public function test_evento_b3_rule_applies_only_to_income(): void
    {
        $dividendos = Category::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Dividendos',
            'slug' => 'dividendos',
            'type' => 'income',
            'is_system' => true,
        ]);

        CategorizationRule::query()->create([
            'workspace_id' => $this->workspace->id,
            'category_id' => $dividendos->id,
            'name' => 'Evento B3',
            'pattern' => 'evento b3',
            'match_type' => 'contains',
            'transaction_type' => 'income',
            'priority' => 10,
            'is_active' => true,
        ]);

        config(['financial.default_categorization_patterns' => []]);

        $service = app(CategorizationService::class);

        $income = $service->suggest(
            $this->workspace->id,
            'Crédito Evento B3 dividendos',
            null,
            TransactionType::Income,
        );

        $this->assertNotNull($income);
        $this->assertSame($dividendos->id, $income['category_id']);

        $expense = $service->suggest(
            $this->workspace->id,
            'Crédito Evento B3 dividendos',
            null,
            TransactionType::Expense,
        );

        $this->assertNull($expense);
    }
}
