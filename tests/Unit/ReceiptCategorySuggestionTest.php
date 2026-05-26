<?php

namespace Tests\Unit;

use App\Application\Services\ReceiptCategorySuggestionService;
use App\Core\Enums\TransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Categorization\Infrastructure\Models\CategorizationRule;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class ReceiptCategorySuggestionTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
    }

    public function test_suggests_category_from_rule_for_uber(): void
    {
        $transport = Category::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Transporte',
            'slug' => 'transporte',
            'type' => 'expense',
            'is_system' => true,
        ]);

        CategorizationRule::query()->create([
            'workspace_id' => $this->workspace->id,
            'category_id' => $transport->id,
            'name' => 'Uber',
            'pattern' => 'uber',
            'match_type' => 'contains',
            'transaction_type' => 'expense',
            'priority' => 10,
            'is_active' => true,
        ]);

        $result = app(ReceiptCategorySuggestionService::class)->forTransaction(
            $this->workspace->id,
            'Compra Uber UBER *TRIP',
            'Uber',
            TransactionType::Expense,
        );

        $this->assertTrue($result['optional']);
        $this->assertNotNull($result['recommended']);
        $this->assertSame($transport->id, $result['recommended']['category_id']);
    }
}
