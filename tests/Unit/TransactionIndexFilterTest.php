<?php

namespace Tests\Unit;

use App\Core\Enums\TransactionStatus;
use App\Core\Enums\TransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Finance\Application\Actions\CreateTransactionAction;
use Modules\Finance\Application\DTOs\CreateTransactionData;
use Modules\Finance\Application\Services\TransactionIndexFilterService;
use Modules\Finance\Infrastructure\Models\Transaction;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class TransactionIndexFilterTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
    }

    public function test_description_contains_filter(): void
    {
        app(CreateTransactionAction::class)->execute(new CreateTransactionData(
            workspaceId: $this->workspace->id,
            type: TransactionType::Expense,
            amount: 100,
            description: 'Uber viagem centro',
            transactionDate: now()->toDateString(),
        ));

        app(CreateTransactionAction::class)->execute(new CreateTransactionData(
            workspaceId: $this->workspace->id,
            type: TransactionType::Expense,
            amount: 50,
            description: 'Supermercado',
            transactionDate: now()->toDateString(),
        ));

        $request = Request::create('/', 'GET', ['description' => 'uber']);
        $request->attributes->set('workspace_id', $this->workspace->id);

        $query = Transaction::query()->where('workspace_id', $this->workspace->id);
        app(TransactionIndexFilterService::class)->apply($query, $request);

        $this->assertEquals(1, $query->count());
        $this->assertStringContainsString('Uber', $query->first()->description);
    }

    public function test_amount_range_filter(): void
    {
        app(CreateTransactionAction::class)->execute(new CreateTransactionData(
            workspaceId: $this->workspace->id,
            type: TransactionType::Income,
            amount: 500,
            description: 'A',
            transactionDate: now()->toDateString(),
        ));

        $request = Request::create('/', 'GET', ['amount_min' => '200', 'amount_max' => '600']);
        $query = Transaction::query()->where('workspace_id', $this->workspace->id);
        app(TransactionIndexFilterService::class)->apply($query, $request);

        $this->assertEquals(1, $query->count());

        $request2 = Request::create('/', 'GET', ['amount_min' => '600']);
        $query2 = Transaction::query()->where('workspace_id', $this->workspace->id);
        app(TransactionIndexFilterService::class)->apply($query2, $request2);

        $this->assertEquals(0, $query2->count());
    }

    public function test_status_filter(): void
    {
        app(CreateTransactionAction::class)->execute(new CreateTransactionData(
            workspaceId: $this->workspace->id,
            type: TransactionType::Expense,
            amount: 10,
            description: 'Pendente',
            transactionDate: now()->toDateString(),
            status: TransactionStatus::Pending,
        ));

        $request = Request::create('/', 'GET', ['status' => 'pending']);
        $query = Transaction::query()->where('workspace_id', $this->workspace->id);
        app(TransactionIndexFilterService::class)->apply($query, $request);

        $this->assertEquals(1, $query->count());
    }
}
