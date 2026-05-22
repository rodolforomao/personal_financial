<?php

namespace Tests\Feature\Phase1;

use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Finance\Infrastructure\Models\Transaction;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class TransactionApiTest extends TestCase
{
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
    }

    public function test_store_transaction_with_auto_categorization(): void
    {
        $response = $this->postJson('/api/v1/transactions', [
            'type' => 'expense',
            'amount' => 99.90,
            'description' => 'Plano ChatGPT',
            'transaction_date' => now()->toDateString(),
            'counterparty' => 'OpenAI',
            'status' => 'confirmed',
        ], $this->apiHeaders());

        $response->assertCreated();
        $response->assertJsonPath('category.slug', 'ia');

        $this->assertDatabaseHas('transactions', [
            'workspace_id' => $this->workspace->id,
            'counterparty' => 'OpenAI',
        ]);
    }

    public function test_store_recurring_transaction_creates_recurring_item(): void
    {
        $category = Category::query()->where('slug', 'ia')->first();

        $response = $this->postJson('/api/v1/transactions', [
            'type' => 'expense',
            'amount' => 20,
            'description' => 'Netflix',
            'transaction_date' => now()->toDateString(),
            'category_id' => $category->id,
            'is_recurring' => true,
            'recurrence_frequency' => 'monthly',
            'status' => 'confirmed',
        ], $this->apiHeaders());

        $response->assertCreated();
        $response->assertJsonPath('is_recurring', true);
        $response->assertJsonPath('recurrence_frequency', 'monthly');

        $transaction = Transaction::query()->latest('id')->first();
        $this->assertNotNull($transaction->recurring_item_id);
        $this->assertDatabaseHas('recurring_items', [
            'workspace_id' => $this->workspace->id,
            'title' => 'Netflix',
            'frequency' => 'monthly',
        ]);
    }
}
