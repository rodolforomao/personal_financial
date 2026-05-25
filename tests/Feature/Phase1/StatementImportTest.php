<?php

namespace Tests\Feature\Phase1;

use App\Core\Enums\TransactionStatus;
use App\Core\Enums\TransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Application\Services\StatementImportService;
use Modules\Finance\Infrastructure\Models\StatementLine;
use Modules\Finance\Infrastructure\Models\Transaction;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class StatementImportTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
    }

    public function test_parse_ofx_creates_lines_with_correct_amounts(): void
    {
        $path = base_path('tests/fixtures/sample.ofx');

        $import = app(StatementImportService::class)->parseOfx(
            $this->workspace->id,
            $this->user,
            $path,
            'sample.ofx',
        );

        $this->assertSame(2, $import->lines_total);

        $expense = $import->lines->first(fn ($l) => $l->type === TransactionType::Expense);
        $this->assertNotNull($expense);
        $this->assertEquals(5000.0, (float) $expense->amount);
        $this->assertTrue(
            str_contains($expense->description, 'Multifilmes')
            || str_contains((string) $expense->counterparty, 'Multifilmes'),
        );
        $this->assertSame('2026-05-21 00:00:00', $expense->transaction_date->format('Y-m-d H:i:s'));
    }

    public function test_parse_ofx_preserves_posted_time(): void
    {
        $ofx = <<<'OFX'
OFXHEADER:100
<OFX>
<STMTTRN>
<TRNAMT>-10.00
<DTPOSTED>20260519143022
<MEMO>Teste hora
<FITID>1
</STMTTRN>
</OFX>
OFX;

        $path = sys_get_temp_dir().'/ofx_time_'.uniqid().'.ofx';
        file_put_contents($path, $ofx);

        try {
            $import = app(StatementImportService::class)->parseOfx(
                $this->workspace->id,
                $this->user,
                $path,
                'time.ofx',
            );

            $line = $import->lines->first();
            $this->assertNotNull($line);
            $this->assertSame('2026-05-19 14:30:22', $line->transaction_date->format('Y-m-d H:i:s'));
        } finally {
            @unlink($path);
        }
    }

    public function test_auto_match_links_existing_transaction(): void
    {
        Transaction::query()->create([
            'workspace_id' => $this->workspace->id,
            'type' => TransactionType::Expense,
            'status' => TransactionStatus::Pending,
            'amount' => 5000,
            'currency' => 'BRL',
            'description' => 'PIX Multifilmes Goiania',
            'counterparty' => 'Multifilmes Goiania',
            'transaction_date' => '2026-05-21',
            'source' => 'manual',
        ]);

        $import = app(StatementImportService::class)->parseOfx(
            $this->workspace->id,
            $this->user,
            base_path('tests/fixtures/sample.ofx'),
            'sample.ofx',
        );

        $suggested = $import->lines()
            ->where('match_status', StatementLine::STATUS_SUGGESTED)
            ->where('amount', 5000)
            ->first();

        $this->assertNotNull($suggested);
        $this->assertNotNull($suggested->transaction_id);
        $this->assertGreaterThanOrEqual(60, $suggested->match_score);
    }
}
