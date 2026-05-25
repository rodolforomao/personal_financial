<?php

namespace Tests\Feature\Phase1;

use App\Core\Enums\TransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Application\Services\TransactionDeduplicationService;
use Modules\Finance\Infrastructure\Models\Transaction;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class TransactionDeduplicationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
    }

    public function test_detects_duplicate_by_description_and_amount(): void
    {
        Transaction::query()->create([
            'workspace_id' => $this->workspace->id,
            'type' => TransactionType::Expense,
            'status' => 'pending',
            'amount' => 100,
            'currency' => 'BRL',
            'description' => 'Almoço equipe escritório',
            'transaction_date' => now()->toDateString(),
            'source' => 'telegram',
        ]);

        $service = app(TransactionDeduplicationService::class);

        $this->assertTrue($service->exists(
            $this->workspace->id,
            TransactionType::Expense,
            100,
            now()->toDateString(),
            'Almoço equipe',
        ));
    }
}
