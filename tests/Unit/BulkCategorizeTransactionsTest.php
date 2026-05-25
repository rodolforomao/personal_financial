<?php

namespace Tests\Unit;

use App\Core\Enums\TransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Categorization\Application\Services\BulkCategorizeTransactionsService;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Finance\Infrastructure\Models\Transaction;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class BulkCategorizeTransactionsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();

        foreach (['transporte', 'viagem', 'contas-recorrentes'] as $slug) {
            Category::query()->create([
                'workspace_id' => $this->workspace->id,
                'name' => $slug,
                'slug' => $slug,
                'type' => 'expense',
                'is_system' => true,
            ]);
        }
    }

    public function test_bulk_categorize_assigns_categories_from_description_patterns(): void
    {
        $uber = Transaction::query()->create([
            'workspace_id' => $this->workspace->id,
            'type' => TransactionType::Expense,
            'status' => 'pending',
            'amount' => 26.94,
            'currency' => 'BRL',
            'description' => 'Compra no debito: Uber UBER *TRIP HELP.U SP BRA',
            'transaction_date' => '2026-05-10',
            'source' => 'ofx',
        ]);

        $hotel = Transaction::query()->create([
            'workspace_id' => $this->workspace->id,
            'type' => TransactionType::Expense,
            'status' => 'pending',
            'amount' => 450,
            'currency' => 'BRL',
            'description' => 'HOTEL IBIS SAO PAULO',
            'transaction_date' => '2026-05-11',
            'source' => 'ofx',
        ]);

        $debito = Transaction::query()->create([
            'workspace_id' => $this->workspace->id,
            'type' => TransactionType::Expense,
            'status' => 'pending',
            'amount' => 49.90,
            'currency' => 'BRL',
            'description' => 'DEBITO AUTOMATICO NETFLIX',
            'transaction_date' => '2026-05-12',
            'source' => 'ofx',
        ]);

        Transaction::query()->create([
            'workspace_id' => $this->workspace->id,
            'type' => TransactionType::Expense,
            'status' => 'pending',
            'amount' => 10,
            'currency' => 'BRL',
            'description' => 'Lançamento genérico XYZ',
            'transaction_date' => '2026-05-13',
            'source' => 'manual',
        ]);

        $result = app(BulkCategorizeTransactionsService::class)->run($this->workspace->id);

        $this->assertSame(4, $result['total']);
        $this->assertSame(3, $result['categorized']);
        $this->assertSame(1, $result['unchanged']);
        $this->assertSame('transporte', $uber->fresh()->category?->slug);
        $this->assertSame('viagem', $hotel->fresh()->category?->slug);
        $this->assertSame('contas-recorrentes', $debito->fresh()->category?->slug);
    }
}
