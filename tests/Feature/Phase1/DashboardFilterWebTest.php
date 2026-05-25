<?php

namespace Tests\Feature\Phase1;

use App\Core\Enums\CompanyType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Finance\Application\Services\DashboardFilterService;
use Modules\Operations\Infrastructure\Models\Operation;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class DashboardFilterWebTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
    }

    public function test_dashboard_filter_persists_in_session(): void
    {
        $company = Company::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Op Co',
            'type' => CompanyType::Own,
        ]);

        $operation = Operation::query()->create([
            'workspace_id' => $this->workspace->id,
            'company_id' => $company->id,
            'name' => 'Airbnb',
            'slug' => 'airbnb',
        ]);

        $this->actingAs($this->user)
            ->withSession(['workspace_id' => $this->workspace->id])
            ->post(route('dashboard.filter'), [
                'include_all_operations' => '1',
                'exclude_operation_ids' => [(string) $operation->id],
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertTrue(session(DashboardFilterService::SESSION_INCLUDE_ALL));
        $this->assertEquals([$operation->id], session(DashboardFilterService::SESSION_EXCLUDE_IDS));
    }
}
