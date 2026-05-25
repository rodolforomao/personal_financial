<?php

namespace Tests\Unit;

use App\Core\Enums\TransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Finance\Application\Services\TransactionBulkActionService;
use Modules\Finance\Infrastructure\Models\Transaction;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class TransactionBulkActionTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
    }

    public function test_bulk_sets_category_and_clears_operation(): void
    {
        $category = Category::query()
            ->where('workspace_id', $this->workspace->id)
            ->where('slug', 'transporte')
            ->firstOrFail();

        $tx = Transaction::query()->create([
            'workspace_id' => $this->workspace->id,
            'type' => TransactionType::Expense,
            'amount' => 50,
            'description' => 'Uber trip',
            'transaction_date' => '2026-05-10',
        ]);

        $service = app(TransactionBulkActionService::class);
        $result = $service->apply($this->workspace->id, [$tx->id], [
            'category_id' => $category->id,
            'funding_source' => 'inter',
        ]);

        $this->assertSame(1, $result['updated']);
        $fresh = $tx->fresh();
        $this->assertSame($category->id, $fresh->category_id);
        $this->assertSame('inter', $fresh->funding_source);
    }

    public function test_bulk_soft_deletes_transactions(): void
    {
        $ids = [];
        foreach ([10.0, 20.0] as $amount) {
            $ids[] = Transaction::query()->create([
                'workspace_id' => $this->workspace->id,
                'type' => TransactionType::Expense,
                'amount' => $amount,
                'description' => 'Test',
                'transaction_date' => '2026-05-11',
            ])->id;
        }

        $result = app(TransactionBulkActionService::class)->delete($this->workspace->id, $ids);

        $this->assertSame(2, $result['deleted']);
        $this->assertSame(0, Transaction::query()->whereIn('id', $ids)->count());
    }
}
