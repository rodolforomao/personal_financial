<?php

namespace Tests\Feature\Phase1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class ReportsWebTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
    }

    public function test_reports_page_renders_for_authenticated_user(): void
    {
        $this->actingAs($this->user)
            ->withSession(['workspace_id' => $this->workspace->id])
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Relatórios')
            ->assertSee('Filtros do relatório')
            ->assertSee('Baixar XLSX')
            ->assertSee('Baixar PDF');
    }

    public function test_reports_export_xlsx_returns_spreadsheet(): void
    {
        $this->actingAs($this->user)
            ->withSession(['workspace_id' => $this->workspace->id])
            ->get(route('reports.export', ['format' => 'xlsx']))
            ->assertOk()
            ->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );
    }
}
