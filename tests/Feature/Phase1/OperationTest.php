<?php

namespace Tests\Feature\Phase1;

use App\Core\Enums\CompanyType;
use App\Core\Enums\TransactionStatus;
use App\Core\Enums\TransactionType;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Finance\Application\Actions\CreateTransactionAction;
use Modules\Finance\Application\DTOs\CreateTransactionData;
use Modules\Finance\Application\Services\CashFlowService;
use Modules\Operations\Infrastructure\Models\Operation;
use Modules\Operations\Infrastructure\Models\OperationUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class OperationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
    }

    public function test_operation_transactions_excluded_from_main_dashboard(): void
    {
        $company = Company::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Residencial Oliveiras',
            'type' => CompanyType::Own,
        ]);

        $operation = Operation::query()->create([
            'workspace_id' => $this->workspace->id,
            'company_id' => $company->id,
            'name' => 'Residencial Oliveiras',
            'slug' => 'residencial-oliveiras',
        ]);

        $unit = OperationUnit::query()->create([
            'operation_id' => $operation->id,
            'name' => 'Apto 101',
            'code' => '101',
        ]);

        app(CreateTransactionAction::class)->execute(new CreateTransactionData(
            workspaceId: $this->workspace->id,
            type: TransactionType::Income,
            amount: 3000,
            description: 'Hospedagem Airbnb',
            transactionDate: now()->toDateString(),
            operationId: $operation->id,
            operationUnitId: $unit->id,
            companyId: $company->id,
            status: TransactionStatus::Confirmed,
        ));

        app(CreateTransactionAction::class)->execute(new CreateTransactionData(
            workspaceId: $this->workspace->id,
            type: TransactionType::Income,
            amount: 1000,
            description: 'Salário geral',
            transactionDate: now()->toDateString(),
            status: TransactionStatus::Confirmed,
        ));

        $dashboard = app(CashFlowService::class)->dashboard($this->workspace->id);

        $this->assertEquals(1000.0, (float) $dashboard['current_month']->total_income);
    }

    public function test_web_can_create_operation_with_units(): void
    {
        $company = Company::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Residencial Oliveiras',
            'type' => CompanyType::Own,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['workspace_id' => $this->workspace->id])
            ->post(route('operations.store'), [
                'name' => 'Residencial Oliveiras',
                'company_id' => $company->id,
            ]);

        $operation = Operation::query()->where('slug', 'residencial-oliveiras')->first();
        $response->assertRedirect(route('operations.show', $operation));

        $this->actingAs($this->user)
            ->withSession(['workspace_id' => $this->workspace->id])
            ->post(route('operations.units.store', $operation), [
                'name' => 'Apto 201',
                'code' => '201',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('operation_units', [
            'operation_id' => $operation->id,
            'name' => 'Apto 201',
        ]);
    }
}
