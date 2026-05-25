<?php

namespace Tests\Unit;

use App\Core\Enums\TransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Application\Services\StatementImportService;
use Modules\Finance\Application\Services\StatementNettedPairService;
use Modules\Finance\Infrastructure\Models\StatementImport;
use Modules\Finance\Infrastructure\Models\StatementLine;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class StatementNettedPairTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
    }

    public function test_purchase_and_estorno_same_day_amount_are_netted(): void
    {
        $import = StatementImport::query()->create([
            'workspace_id' => $this->workspace->id,
            'original_name' => 'inter.ofx',
            'format' => 'ofx',
            'status' => StatementImport::STATUS_PENDING,
        ]);

        StatementLine::query()->create([
            'statement_import_id' => $import->id,
            'transaction_date' => '2026-05-10',
            'amount' => 26.94,
            'type' => TransactionType::Expense,
            'description' => 'Compra no debito: "No estabelecimento Uber UBER *TRIP HELP.U SP BRA"',
            'counterparty' => 'Uber Uber *trip Help.u Sp Bra',
            'match_status' => StatementLine::STATUS_UNMATCHED,
        ]);

        StatementLine::query()->create([
            'statement_import_id' => $import->id,
            'transaction_date' => '2026-05-10',
            'amount' => 26.94,
            'type' => TransactionType::Income,
            'description' => 'Estorno: "Estorno no estabelecimento nao informado"',
            'counterparty' => 'Compra cartão',
            'match_status' => StatementLine::STATUS_UNMATCHED,
        ]);

        $marked = app(StatementNettedPairService::class)->markNettedPairs($import);

        $this->assertSame(2, $marked);
        $this->assertSame(0, $import->lines()->visible()->count());
        $this->assertSame(2, $import->lines()->where('match_status', StatementLine::STATUS_NETTED)->count());
    }

    public function test_duplicate_estorno_near_amounts_are_netted(): void
    {
        $import = StatementImport::query()->create([
            'workspace_id' => $this->workspace->id,
            'original_name' => 'inter.ofx',
            'format' => 'ofx',
            'status' => StatementImport::STATUS_PENDING,
        ]);

        foreach ([31.93, 31.98] as $amount) {
            StatementLine::query()->create([
                'statement_import_id' => $import->id,
                'transaction_date' => '2026-05-10',
                'amount' => $amount,
                'type' => TransactionType::Income,
                'description' => 'Estorno: "Estorno no estabelecimento nao informado"',
                'counterparty' => 'Compra cartão',
                'match_status' => StatementLine::STATUS_UNMATCHED,
            ]);
        }

        $marked = app(StatementNettedPairService::class)->markNettedPairs($import);

        $this->assertSame(2, $marked);
        $this->assertSame(0, $import->lines()->visible()->count());
    }

    public function test_ofx_import_hides_netted_uber_pair(): void
    {
        $import = app(StatementImportService::class)->parseOfx(
            $this->workspace->id,
            $this->user,
            base_path('tests/fixtures/inter-uber.ofx'),
            'inter-uber.ofx',
        );

        $this->assertSame(4, $import->netted_count);
        $this->assertGreaterThanOrEqual(1, $import->lines_total);

        $visible = $import->lines->filter(fn ($l) => $l->match_status !== StatementLine::STATUS_NETTED);
        $this->assertFalse(
            $visible->contains(fn ($l) => str_contains(strtolower($l->description), 'estorno')
                && (float) $l->amount === 26.94),
        );
        $this->assertFalse(
            $visible->contains(fn ($l) => (float) $l->amount === 26.94 && $l->type === TransactionType::Expense),
        );
    }
}
