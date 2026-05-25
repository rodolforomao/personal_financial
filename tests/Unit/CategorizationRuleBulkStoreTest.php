<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Categorization\Infrastructure\Models\CategorizationRule;
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

        $controller->store($request);

        $this->assertSame(3, CategorizationRule::query()->where('workspace_id', $this->workspace->id)->count());

        $uber = CategorizationRule::query()->where('pattern', 'uber')->first();
        $this->assertNotNull($uber);
        $this->assertSame($category->id, $uber->category_id);
        $this->assertSame('Uber', $uber->name);
        $this->assertSame('expense', $uber->transaction_type);
    }
}
