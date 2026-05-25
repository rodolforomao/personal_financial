<?php

namespace Tests\Unit;

use App\Core\Enums\CompanyType;
use App\Core\Enums\TransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Integrations\Application\Services\ReceiptExtractionService;
use Modules\Operations\Infrastructure\Models\Operation;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class ReceiptDraftSupplementMergeTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
    }

    public function test_apply_draft_supplement_resolves_entities_by_name(): void
    {
        $category = Category::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Shilene',
            'slug' => 'shilene',
            'type' => 'expense',
            'color' => '#333',
            'is_system' => false,
        ]);

        $company = Company::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Pessoal',
            'type' => CompanyType::Own,
            'status' => 'active',
        ]);

        $operation = Operation::query()->create([
            'workspace_id' => $this->workspace->id,
            'company_id' => $company->id,
            'name' => 'Geral',
            'slug' => 'geral',
        ]);

        $result = app(ReceiptExtractionService::class)->applyDraftSupplement(
            [
                'type' => 'expense',
                'amount' => 140,
                'description' => 'Pix enviado',
                'counterparty' => 'Priscila Batista',
            ],
            'Contraparte: presente da shilene. Categoria: shilene. Empresa pessoal, operação geral.',
            $this->workspace->id,
        );

        $this->assertTrue($result['changed']);
        $extracted = $result['extracted'];
        $this->assertSame('presente da shilene', $extracted['counterparty']);
        $this->assertSame($category->id, $extracted['category_id']);
        $this->assertSame($company->id, $extracted['company_id']);
        $this->assertSame($operation->id, $extracted['operation_id']);
    }

    public function test_personal_expense_caption_forces_physical_person_general_operation_and_expense_type(): void
    {
        $category = Category::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Compras',
            'slug' => 'compras',
            'type' => 'expense',
            'color' => '#333',
            'is_system' => false,
        ]);
        $company = Company::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Pessoa Física',
            'type' => CompanyType::Own,
            'status' => 'active',
        ]);
        $operation = Operation::query()->create([
            'workspace_id' => $this->workspace->id,
            'company_id' => $company->id,
            'name' => 'Geral',
            'slug' => 'geral',
        ]);

        $result = app(ReceiptExtractionService::class)->applyDraftSupplement(
            [
                'type' => TransactionType::Income->value,
                'amount' => 668.98,
                'description' => 'Nota fiscal eletrônica',
                'counterparty' => 'EBAZAR.COM.BR. LTDA',
            ],
            'despeza pessoal, compra de tenis',
            $this->workspace->id,
        );

        $this->assertTrue($result['changed']);
        $extracted = $result['extracted'];
        $this->assertSame(TransactionType::Expense->value, $extracted['type']);
        $this->assertSame($company->id, $extracted['company_id']);
        $this->assertSame($operation->id, $extracted['operation_id']);
        $this->assertSame($category->id, $extracted['category_id']);
        $this->assertSame('despeza pessoal, compra de tenis', $extracted['description']);
    }

    public function test_apply_draft_supplement_resolves_combined_category_and_operation_without_colon(): void
    {
        $category = Category::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Oec empreendimentos',
            'slug' => 'oec-empreendimentos',
            'type' => 'income',
            'color' => '#333',
            'is_system' => false,
        ]);

        $company = Company::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'OEC',
            'type' => CompanyType::Client,
            'status' => 'active',
        ]);

        $operation = Operation::query()->create([
            'workspace_id' => $this->workspace->id,
            'company_id' => $company->id,
            'name' => 'Oec empreendimentos',
            'slug' => 'oec-empreendimentos',
        ]);

        $result = app(ReceiptExtractionService::class)->applyDraftSupplement(
            [
                'type' => TransactionType::Income->value,
                'amount' => 1500,
                'description' => 'Receita oec empreendimentos, responsabilidades em atraso',
                'counterparty' => 'ENTERNET P LTDA - ME',
            ],
            'Categoria e operacao Oec empreendimentos',
            $this->workspace->id,
        );

        $this->assertTrue($result['changed']);
        $extracted = $result['extracted'];
        $this->assertSame($category->id, $extracted['category_id']);
        $this->assertSame($operation->id, $extracted['operation_id']);
        $this->assertSame($company->id, $extracted['company_id']);
        $this->assertSame('Receita oec empreendimentos, responsabilidades em atraso', $extracted['description']);
    }
}
