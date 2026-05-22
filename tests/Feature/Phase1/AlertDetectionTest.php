<?php

namespace Tests\Feature\Phase1;

use App\Core\Enums\TransactionType;
use Modules\Alerts\Application\Services\AlertDetectionService;
use Modules\Alerts\Infrastructure\Models\Alert;
use Modules\Finance\Infrastructure\Models\RecurringItem;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class AlertDetectionTest extends TestCase
{
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
    }

    public function test_detects_missing_recurring_revenue(): void
    {
        RecurringItem::query()->create([
            'workspace_id' => $this->workspace->id,
            'type' => TransactionType::Income->value,
            'title' => 'Mensalidade Cliente X',
            'amount' => 15000,
            'frequency' => 'monthly',
            'next_due_at' => now()->subDays(5),
            'is_active' => true,
        ]);

        app(AlertDetectionService::class)->scan($this->workspace->id);

        $this->assertTrue(
            Alert::query()
                ->where('workspace_id', $this->workspace->id)
                ->where('type', 'missing_revenue')
                ->exists()
        );
    }
}
