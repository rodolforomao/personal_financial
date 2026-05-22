<?php

namespace Tests\Feature\Phase1;

use App\Core\Enums\TransactionStatus;
use App\Core\Enums\TransactionType;
use Modules\Finance\Application\Actions\CreateTransactionAction;
use Modules\Finance\Application\DTOs\CreateTransactionData;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
    }

    public function test_dashboard_returns_cash_flow_and_forecast(): void
    {
        app(CreateTransactionAction::class)->execute(new CreateTransactionData(
            workspaceId: $this->workspace->id,
            type: TransactionType::Income,
            amount: 5000,
            description: 'Receita cliente',
            transactionDate: now()->toDateString(),
            status: TransactionStatus::Confirmed,
        ));

        $response = $this->getJson('/api/v1/dashboard', $this->apiHeaders());

        $response->assertOk();
        $response->assertJsonStructure([
            'cash_flow' => ['current_month'],
            'forecast' => ['projected_balance', 'risk_level'],
            'patrimony',
            'projects',
        ]);
    }
}
