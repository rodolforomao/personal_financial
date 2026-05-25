<?php

namespace Tests\Unit;

use App\Application\Services\ReceiptCaptionContextResolver;
use App\Core\Enums\CompanyType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Operations\Infrastructure\Models\Operation;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class ReceiptCaptionContextResolverTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
    }

    public function test_resolves_airbnb_category_and_residencial_operation(): void
    {
        Category::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Aluguel - Airbnb',
            'slug' => 'aluguel-airbnb',
            'type' => 'income',
            'color' => '#ff5a5f',
            'is_system' => true,
        ]);

        $company = Company::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Residencial Oliveiras',
            'type' => CompanyType::Client,
            'status' => 'active',
        ]);

        Operation::query()->create([
            'workspace_id' => $this->workspace->id,
            'company_id' => $company->id,
            'name' => 'Residencial Oliveiras',
            'slug' => 'residencial-oliveiras',
            'exclude_from_main_dashboard' => true,
        ]);

        $result = app(ReceiptCaptionContextResolver::class)->resolve(
            $this->workspace->id,
            'Airbnb, residencial oliveiras, nubank, pix',
        );

        $this->assertSame('aluguel-airbnb', $result['category_slug']);
        $this->assertSame('Aluguel - Airbnb', $result['category_name']);
        $this->assertSame($company->id, $result['company_id']);
        $this->assertNotNull($result['operation_id']);
    }
}
