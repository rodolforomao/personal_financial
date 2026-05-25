<?php

namespace Tests\Unit;

use App\Core\Enums\TransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Application\Services\TransactionEstornoPairCleanupService;
use Modules\Finance\Infrastructure\Models\Transaction;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class TransactionEstornoPairCleanupTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
    }

    public function test_pairs_uber_purchase_with_estorno_same_amount(): void
    {
        Transaction::query()->create([
            'workspace_id' => $this->workspace->id,
            'type' => TransactionType::Expense,
            'amount' => 26.94,
            'description' => 'Compra no debito: "No estabelecimento Uber UBER *TRIP HELP.U SP BRA"',
            'counterparty' => 'Uber Uber *trip Help.u Sp Bra',
            'transaction_date' => '2026-05-10',
            'source' => 'statement_import',
        ]);

        Transaction::query()->create([
            'workspace_id' => $this->workspace->id,
            'type' => TransactionType::Income,
            'amount' => 26.94,
            'description' => 'Estorno: "Estorno no estabelecimento nao informado"',
            'counterparty' => 'Compra cartão',
            'transaction_date' => '2026-05-10',
            'source' => 'statement_import',
        ]);

        Transaction::query()->create([
            'workspace_id' => $this->workspace->id,
            'type' => TransactionType::Expense,
            'amount' => 31.93,
            'description' => 'Compra no debito: "No estabelecimento Uber UBER *TRIP HELP.U SP BRA"',
            'counterparty' => 'Uber Uber *trip Help.u Sp Bra',
            'transaction_date' => '2026-05-10',
            'source' => 'statement_import',
        ]);

        $service = app(TransactionEstornoPairCleanupService::class);
        $preview = $service->preview($this->workspace->id);

        $this->assertSame(1, $preview['pair_count']);
        $this->assertSame(2, $preview['transaction_count']);

        $result = $service->removeNettedPairs($this->workspace->id);

        $this->assertSame(2, $result['removed']);
        $this->assertSame('purchase_estorno', $result['pairs'][0]['kind']);
        $this->assertSame(1, Transaction::query()->where('workspace_id', $this->workspace->id)->count());
        $this->assertSame(31.93, (float) Transaction::query()->value('amount'));
    }

    public function test_pairs_uber_expense_without_compra_no_debito_text(): void
    {
        Transaction::query()->create([
            'workspace_id' => $this->workspace->id,
            'type' => TransactionType::Expense,
            'amount' => 26.94,
            'description' => 'UBER *TRIP HELP.U SP BRA',
            'counterparty' => 'Uber',
            'transaction_date' => '2026-05-10',
            'source' => 'manual',
        ]);

        Transaction::query()->create([
            'workspace_id' => $this->workspace->id,
            'type' => TransactionType::Income,
            'amount' => 26.94,
            'description' => 'Estorno: "Estorno no estabelecimento nao informado"',
            'counterparty' => 'Compra cartão',
            'transaction_date' => '2026-05-10',
            'source' => 'statement_import',
        ]);

        $result = app(TransactionEstornoPairCleanupService::class)->removeNettedPairs($this->workspace->id);

        $this->assertSame(2, $result['removed']);
    }

    public function test_removes_duplicate_estorno_transactions(): void
    {
        foreach ([31.93, 31.98] as $amount) {
            Transaction::query()->create([
                'workspace_id' => $this->workspace->id,
                'type' => TransactionType::Income,
                'amount' => $amount,
                'description' => 'Estorno: "Estorno no estabelecimento nao informado"',
                'counterparty' => 'Compra cartão',
                'transaction_date' => '2026-05-10',
                'source' => 'statement_import',
            ]);
        }

        $result = app(TransactionEstornoPairCleanupService::class)
            ->removeNettedPairs($this->workspace->id);

        $this->assertSame(2, $result['removed']);
        $this->assertSame('duplicate_estorno', $result['pairs'][0]['kind']);
    }
}
