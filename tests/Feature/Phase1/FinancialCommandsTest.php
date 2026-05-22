<?php

namespace Tests\Feature\Phase1;

use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class FinancialCommandsTest extends TestCase
{
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
    }

    public function test_financial_daily_command_runs(): void
    {
        $this->artisan('financial:daily', ['--workspace' => $this->workspace->id])
            ->assertSuccessful();
    }

    public function test_financial_scan_alerts_command_runs(): void
    {
        $this->artisan('financial:scan-alerts', ['--workspace' => $this->workspace->id])
            ->assertSuccessful();
    }
}
