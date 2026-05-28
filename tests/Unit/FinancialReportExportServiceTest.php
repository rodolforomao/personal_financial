<?php

namespace Tests\Unit;

use App\Core\Enums\TransactionStatus;
use App\Core\Enums\TransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Finance\Application\Services\FinancialReportExportService;
use Modules\Finance\Infrastructure\Models\Transaction;
use Modules\Operations\Infrastructure\Models\Operation;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class FinancialReportExportServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
    }

    public function test_rows_ordered_by_date_with_sequential_numbers(): void
    {
        $category = Category::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Aluguel',
            'slug' => 'aluguel',
            'type' => 'income',
        ]);

        $operation = Operation::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Airbnb',
            'slug' => 'airbnb',
        ]);

        Transaction::query()->create([
            'workspace_id' => $this->workspace->id,
            'type' => TransactionType::Expense,
            'status' => TransactionStatus::Confirmed,
            'amount' => 100,
            'description' => 'Primeira despesa',
            'counterparty' => 'Fornecedor A',
            'category_id' => $category->id,
            'operation_id' => $operation->id,
            'transaction_date' => '2026-05-10',
        ]);

        Transaction::query()->create([
            'workspace_id' => $this->workspace->id,
            'type' => TransactionType::Income,
            'status' => TransactionStatus::Confirmed,
            'amount' => 500,
            'description' => 'Receita',
            'counterparty' => 'Cliente B',
            'transaction_date' => '2026-05-15',
        ]);

        Transaction::query()->create([
            'workspace_id' => $this->workspace->id,
            'type' => TransactionType::Expense,
            'status' => TransactionStatus::Confirmed,
            'amount' => 50,
            'description' => 'Segunda despesa',
            'transaction_date' => '2026-05-20',
        ]);

        $request = Request::create('/reports/export', 'GET');
        $request->attributes->set('workspace_id', $this->workspace->id);

        $data = app(FinancialReportExportService::class)->dataset($this->workspace->id, $request);

        $this->assertCount(3, $data['rows']);
        $this->assertSame('D-00001', $data['rows'][0]['number']);
        $this->assertSame('10/05/2026', $data['rows'][0]['date']);
        $this->assertSame('Primeira despesa', $data['rows'][0]['description']);
        $this->assertSame('Aluguel', $data['rows'][0]['category']);
        $this->assertStringContainsString('Airbnb', $data['rows'][0]['classification']);
        $this->assertSame('Fornecedor A', $data['rows'][0]['paid_by']);
        $this->assertSame('R-00001', $data['rows'][1]['number']);
        $this->assertSame('D-00002', $data['rows'][2]['number']);
    }
}
