<?php

namespace Tests\Feature\Phase1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Application\Services\StatementAttachmentImportService;
use Modules\Finance\Infrastructure\Models\StatementImport;
use Modules\Finance\Infrastructure\Models\Transaction;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class StatementAttachmentImportTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
    }

    public function test_ofx_attachment_imports_transactions_and_returns_review_links(): void
    {
        $result = app(StatementAttachmentImportService::class)->importAttachment(
            $this->workspace->id,
            $this->user,
            base_path('tests/fixtures/sample.ofx'),
            'sample.ofx',
            'application/x-ofx',
        );

        $this->assertTrue($result['handled']);
        $this->assertSame(2, $result['imported_count']);
        $this->assertStringContainsString('/statements/'.$result['import']->id.'/reconcile', $result['review_url']);
        $this->assertStringContainsString('statement_import_id='.$result['import']->id, $result['bulk_url']);

        $import = StatementImport::query()->firstOrFail();
        $this->assertSame(2, $import->imported_count);

        $this->assertSame(
            2,
            Transaction::query()
                ->where('workspace_id', $this->workspace->id)
                ->where('metadata->statement_import_id', $import->id)
                ->count(),
        );
    }

    public function test_csv_attachment_imports_when_columns_can_be_detected(): void
    {
        $path = sys_get_temp_dir().'/statement_'.uniqid().'.csv';
        file_put_contents($path, "Data,Valor,Descricao\n2026-05-20,-15.50,Cafe\n");

        try {
            $result = app(StatementAttachmentImportService::class)->importAttachment(
                $this->workspace->id,
                $this->user,
                $path,
                'extrato.csv',
                'text/csv',
            );

            $this->assertTrue($result['handled']);
            $this->assertSame(1, $result['imported_count']);
            $this->assertSame(
                1,
                Transaction::query()
                    ->where('workspace_id', $this->workspace->id)
                    ->where('metadata->statement_import_id', $result['import']->id)
                    ->count(),
            );
        } finally {
            @unlink($path);
        }
    }
}
