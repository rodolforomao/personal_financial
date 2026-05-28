<?php

namespace Tests\Unit;

use App\Core\Enums\TransactionStatus;
use App\Core\Enums\TransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Finance\Application\Services\FinancialReportService;
use Modules\Finance\Infrastructure\Models\Transaction;
use Modules\Operations\Infrastructure\Models\Operation;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class FinancialReportServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
    }

    public function test_build_without_filters_aggregates_workspace(): void
    {
        Transaction::query()->create([
            'workspace_id' => $this->workspace->id,
            'type' => TransactionType::Income,
            'status' => TransactionStatus::Confirmed,
            'amount' => 1000,
            'description' => 'Receita geral',
            'transaction_date' => now(),
        ]);

        Transaction::query()->create([
            'workspace_id' => $this->workspace->id,
            'type' => TransactionType::Expense,
            'status' => TransactionStatus::Reconciled,
            'amount' => 300,
            'description' => 'Despesa geral',
            'transaction_date' => now(),
        ]);

        $operation = Operation::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Airbnb',
            'slug' => 'airbnb',
        ]);

        Transaction::query()->create([
            'workspace_id' => $this->workspace->id,
            'operation_id' => $operation->id,
            'type' => TransactionType::Expense,
            'status' => TransactionStatus::Confirmed,
            'amount' => 200,
            'description' => 'Limpeza',
            'transaction_date' => now(),
        ]);

        Transaction::query()->create([
            'workspace_id' => $this->workspace->id,
            'type' => TransactionType::Expense,
            'status' => TransactionStatus::Pending,
            'amount' => 9999,
            'description' => 'Ignorado',
            'transaction_date' => now(),
        ]);

        $request = Request::create('/reports', 'GET');
        $request->attributes->set('workspace_id', $this->workspace->id);

        $report = app(FinancialReportService::class)->build($this->workspace->id, $request);

        $this->assertEquals(1000.0, $report['totals']['income']);
        $this->assertEquals(500.0, $report['totals']['expense']);
        $this->assertEquals(500.0, $report['totals']['net']);
        $this->assertEquals(3, $report['totals']['transaction_count']);
        $this->assertCount(2, $report['by_operation']);
    }

    public function test_build_filters_by_operation(): void
    {
        $operation = Operation::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Loja',
            'slug' => 'loja',
        ]);

        Transaction::query()->create([
            'workspace_id' => $this->workspace->id,
            'operation_id' => $operation->id,
            'type' => TransactionType::Income,
            'status' => TransactionStatus::Confirmed,
            'amount' => 400,
            'description' => 'Venda',
            'transaction_date' => now(),
        ]);

        Transaction::query()->create([
            'workspace_id' => $this->workspace->id,
            'type' => TransactionType::Income,
            'status' => TransactionStatus::Confirmed,
            'amount' => 600,
            'description' => 'Outra',
            'transaction_date' => now(),
        ]);

        $request = Request::create('/reports', 'GET', ['operation_id' => (string) $operation->id]);
        $request->attributes->set('workspace_id', $this->workspace->id);

        $report = app(FinancialReportService::class)->build($this->workspace->id, $request);

        $this->assertEquals(400.0, $report['totals']['income']);
        $this->assertEquals(1, $report['totals']['transaction_count']);
    }
}
