<?php

namespace Tests\Unit;

use App\Core\Enums\CompanyType;
use App\Core\Enums\TransactionStatus;
use App\Core\Enums\TransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Finance\Application\DTOs\DashboardFilter;
use Modules\Finance\Application\Services\CashFlowService;
use Modules\Finance\Application\Actions\CreateTransactionAction;
use Modules\Finance\Application\DTOs\CreateTransactionData;
use Modules\Operations\Infrastructure\Models\Operation;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class DashboardFilterScopeTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
    }

    public function test_consolidated_excludes_satellite_operations_by_default(): void
    {
        $operation = $this->createOperation(excludeFromDashboard: true);

        $this->createTx(amount: 500, operationId: $operation->id);
        $this->createTx(amount: 1000);

        $dashboard = app(CashFlowService::class)->dashboard(
            $this->workspace->id,
            DashboardFilter::consolidated(),
        );

        $this->assertEquals(1000.0, (float) $dashboard['current_month']->total_income);
    }

    public function test_include_all_operations_sums_everything(): void
    {
        $operation = $this->createOperation(excludeFromDashboard: true);

        $this->createTx(amount: 500, operationId: $operation->id);
        $this->createTx(amount: 1000);

        $dashboard = app(CashFlowService::class)->dashboard(
            $this->workspace->id,
            DashboardFilter::allOperations(),
        );

        $this->assertEquals(1500.0, (float) $dashboard['current_month']->total_income);
    }

    public function test_dashboard_cards_include_reconciled_transactions_like_charts(): void
    {
        $this->createTx(amount: 1000, status: TransactionStatus::Confirmed);
        $this->createTx(amount: 500, status: TransactionStatus::Reconciled);

        $dashboard = app(CashFlowService::class)->dashboard(
            $this->workspace->id,
            DashboardFilter::allOperations(),
        );

        $this->assertEquals(1500.0, (float) $dashboard['current_month']->total_income);
    }

    public function test_dashboard_cards_ignore_pending_transactions(): void
    {
        $this->createTx(amount: 1000, status: TransactionStatus::Confirmed);
        $this->createTx(amount: 500, status: TransactionStatus::Pending);

        $dashboard = app(CashFlowService::class)->dashboard(
            $this->workspace->id,
            DashboardFilter::allOperations(),
        );

        $this->assertEquals(1000.0, (float) $dashboard['current_month']->total_income);
    }

    public function test_exclude_operation_ids_hides_selected_operation(): void
    {
        $hidden = $this->createOperation('Hidden', excludeFromDashboard: false);
        $visible = $this->createOperation('Visible', excludeFromDashboard: false);

        $this->createTx(amount: 200, operationId: $hidden->id);
        $this->createTx(amount: 800, operationId: $visible->id);

        $filter = new DashboardFilter(
            includeAllOperations: true,
            excludeOperationIds: [$hidden->id],
        );

        $dashboard = app(CashFlowService::class)->dashboard($this->workspace->id, $filter);

        $this->assertEquals(800.0, (float) $dashboard['current_month']->total_income);
    }

    public function test_consolidated_includes_operation_marked_visible_on_dashboard(): void
    {
        $operation = $this->createOperation(excludeFromDashboard: false);

        $this->createTx(amount: 700, operationId: $operation->id);
        $this->createTx(amount: 300);

        $dashboard = app(CashFlowService::class)->dashboard(
            $this->workspace->id,
            DashboardFilter::consolidated(),
        );

        $this->assertEquals(1000.0, (float) $dashboard['current_month']->total_income);
    }

    protected function createOperation(string $name = 'Residencial', bool $excludeFromDashboard = true): Operation
    {
        $company = Company::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => $name,
            'type' => CompanyType::Own,
        ]);

        return Operation::query()->create([
            'workspace_id' => $this->workspace->id,
            'company_id' => $company->id,
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'exclude_from_main_dashboard' => $excludeFromDashboard,
        ]);
    }

    protected function createTx(
        float $amount,
        ?int $operationId = null,
        TransactionStatus $status = TransactionStatus::Confirmed,
    ): void
    {
        app(CreateTransactionAction::class)->execute(new CreateTransactionData(
            workspaceId: $this->workspace->id,
            type: TransactionType::Income,
            amount: $amount,
            description: 'Test',
            transactionDate: now()->toDateString(),
            operationId: $operationId,
            status: $status,
        ));
    }
}
