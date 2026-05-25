<?php

namespace Tests\Unit;

use App\Core\Enums\CompanyType;
use App\Core\Enums\TransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Finance\Application\Services\DataHygieneService;
use Modules\Finance\Infrastructure\Models\Transaction;
use Modules\Operations\Infrastructure\Models\Operation;
use Modules\Operations\Infrastructure\Models\OperationUnit;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class DataHygieneServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
    }

    public function test_fix_known_company_types(): void
    {
        Company::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Residencial Oliveiras',
            'type' => CompanyType::Partner,
        ]);

        $fixed = app(DataHygieneService::class)->fixKnownCompanyTypes($this->workspace->id);

        $this->assertSame(1, $fixed);
        $this->assertSame(
            CompanyType::Own,
            Company::query()->where('name', 'Residencial Oliveiras')->first()->type,
        );
    }

    public function test_release_geral_transactions(): void
    {
        $geral = Operation::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Geral',
            'slug' => 'geral',
            'exclude_from_main_dashboard' => true,
        ]);

        Transaction::query()->create([
            'workspace_id' => $this->workspace->id,
            'operation_id' => $geral->id,
            'type' => TransactionType::Income,
            'amount' => 100,
            'description' => 'Test',
            'transaction_date' => now(),
            'status' => 'confirmed',
        ]);

        $count = app(DataHygieneService::class)->releaseGeralTransactionsToPersonal($this->workspace->id);

        $this->assertSame(1, $count);
        $this->assertNull(Transaction::query()->first()->operation_id);
    }

    public function test_count_missing_unit(): void
    {
        $op = Operation::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Residencial',
            'slug' => 'res',
        ]);
        OperationUnit::query()->create(['operation_id' => $op->id, 'name' => 'Apt 1']);

        Transaction::query()->create([
            'workspace_id' => $this->workspace->id,
            'operation_id' => $op->id,
            'operation_unit_id' => null,
            'type' => TransactionType::Expense,
            'amount' => 50,
            'description' => 'Limpeza',
            'transaction_date' => now(),
            'status' => 'confirmed',
        ]);

        $this->assertSame(1, app(DataHygieneService::class)->countMissingUnit($this->workspace->id));
    }
}
