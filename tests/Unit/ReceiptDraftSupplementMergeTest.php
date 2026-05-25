<?php

namespace Tests\Unit;

use App\Core\Enums\CompanyType;
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
            'type' => CompanyType::Internal,
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
}
