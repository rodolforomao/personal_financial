<?php

namespace Tests\Feature\Phase1;

use App\Core\Enums\CompanyType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Finance\Application\Services\CltSalaryService;
use Modules\Finance\Infrastructure\Models\CltSalaryPeriod;
use Modules\Finance\Infrastructure\Models\Transaction;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class CltSalaryTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
        Category::query()->firstOrCreate(
            ['workspace_id' => $this->workspace->id, 'slug' => 'salario-clt'],
            ['name' => 'Salário CLT', 'type' => 'income', 'color' => '#157347', 'is_system' => true],
        );
    }

    public function test_confirm_period_creates_income_transaction_with_net_amount(): void
    {
        $employer = Company::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Empresa CLT Ltda',
            'type' => CompanyType::Employer,
        ]);

        $service = app(CltSalaryService::class);
        $salary = $service->upsert($this->workspace->id, [
            'company_id' => $employer->id,
            'gross_amount' => 8000,
            'net_amount' => 6200,
            'payment_day' => 5,
        ]);

        $period = CltSalaryPeriod::query()->create([
            'clt_salary_id' => $salary->id,
            'reference_month' => now()->startOfMonth()->toDateString(),
            'gross_amount' => 8000,
            'net_amount' => 6200,
            'status' => CltSalaryPeriod::STATUS_PENDING,
        ]);

        $payDate = '2026-05-08';

        $result = $service->confirmPeriod($period, $this->user, [
            'net_amount' => 5980.50,
            'gross_amount' => 8000,
            'transaction_date' => $payDate,
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $result['transaction_id'],
            'workspace_id' => $this->workspace->id,
            'type' => 'income',
            'amount' => 5980.50,
            'company_id' => $employer->id,
            'source' => 'clt_salary',
            'transaction_date' => $payDate,
        ]);

        $categoryId = Category::query()->where('slug', 'salario-clt')->value('id');
        $tx = Transaction::query()->find($result['transaction_id']);
        $this->assertSame((int) $categoryId, (int) $tx->category_id);
        $this->assertSame(CltSalaryPeriod::STATUS_CONFIRMED, $period->fresh()->status);
        $this->assertEquals(5980.50, (float) $salary->fresh()->net_amount);
    }

    public function test_suggest_payment_date_uses_payment_day_in_reference_month(): void
    {
        $employer = Company::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Empresa',
            'type' => CompanyType::Employer,
        ]);

        $salary = app(CltSalaryService::class)->upsert($this->workspace->id, [
            'company_id' => $employer->id,
            'net_amount' => 5000,
            'payment_day' => 8,
        ]);

        $date = app(CltSalaryService::class)->suggestPaymentDate($salary, '2026-05-01');

        $this->assertSame('2026-05-08', $date);
    }
}
