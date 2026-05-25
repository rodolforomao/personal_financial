<?php

namespace Tests\Feature\Phase1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use App\Core\Enums\TransactionStatus;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Integrations\Application\Services\ReceiptConfirmationService;
use Modules\Integrations\Application\Services\ReceiptExtractionService;
use Modules\Integrations\Infrastructure\Models\InboundReceiptDraft;
use Modules\Operations\Infrastructure\Models\Operation;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class ReceiptConfirmationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
    }

    public function test_confirm_draft_creates_transaction(): void
    {
        Storage::fake('local');
        $receiptPath = 'receipts/'.$this->workspace->id.'/test.jpg';
        Storage::disk('local')->put($receiptPath, 'fake-image');

        $draft = InboundReceiptDraft::query()->create([
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'channel' => 'telegram',
            'chat_id' => '1722629689',
            'status' => InboundReceiptDraft::STATUS_PENDING,
            'extracted' => [
                'type' => 'expense',
                'amount' => 150.50,
                'date' => now()->toDateString(),
                'description' => 'Almoço equipe',
            ],
            'storage_path' => $receiptPath,
            'mime_type' => 'image/jpeg',
            'expires_at' => now()->addDay(),
        ]);

        $result = app(ReceiptConfirmationService::class)->confirmDraft($draft);

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('Comprovante anexado', $result['message']);
        $this->assertDatabaseHas('transactions', [
            'workspace_id' => $this->workspace->id,
            'source' => 'telegram',
            'amount' => 150.50,
            'status' => TransactionStatus::Confirmed->value,
        ]);
        $this->assertDatabaseHas('documents', [
            'workspace_id' => $this->workspace->id,
            'document_type' => 'receipt',
        ]);
        $this->assertSame(InboundReceiptDraft::STATUS_CONFIRMED, $draft->fresh()->status);
    }

    public function test_confirmation_keywords(): void
    {
        $service = app(ReceiptConfirmationService::class);
        $this->assertTrue($service->isConfirmationYes('sim'));
        $this->assertTrue($service->isConfirmationNo('não'));
    }

    public function test_user_caption_forces_income_type(): void
    {
        $extraction = app(ReceiptExtractionService::class);
        $result = $extraction->applyUserCaption([
            'type' => 'expense',
            'amount' => 4000,
            'description' => 'Pix enviado',
        ], 'Recebimento da venda do notebook Dell 48 gb de ram', $this->workspace->id);

        $this->assertSame('income', $result['type']);
        $this->assertStringContainsString('notebook', $result['description']);
    }

    public function test_supplement_with_colon_applies_free_text_caption(): void
    {
        $extraction = app(ReceiptExtractionService::class);
        $result = $extraction->applyDraftSupplement([
            'type' => 'expense',
            'amount' => 140,
            'description' => 'Pix enviado',
        ], 'Receita: limpeza apartamento 001 — Residencial Oliveiras', $this->workspace->id);

        $this->assertTrue($result['changed']);
        $this->assertSame('income', $result['extracted']['type']);
        $this->assertStringContainsString('limpeza', $result['extracted']['description']);
    }

    public function test_confirm_draft_applies_caption_context(): void
    {
        Storage::fake('local');

        $category = Category::query()->create([
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
            'type' => 'client',
            'status' => 'active',
        ]);

        $operation = Operation::query()->create([
            'workspace_id' => $this->workspace->id,
            'company_id' => $company->id,
            'name' => 'Residencial Oliveiras',
            'slug' => 'residencial-oliveiras',
        ]);

        $draft = InboundReceiptDraft::query()->create([
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'channel' => 'telegram',
            'chat_id' => '1',
            'status' => InboundReceiptDraft::STATUS_PENDING,
            'extracted' => [
                'type' => 'income',
                'amount' => 1941.14,
                'date' => '2026-05-19',
                'description' => 'Airbnb, residencial oliveiras, nubank, pix',
                'category_id' => $category->id,
                'category_name' => $category->name,
                'company_id' => $company->id,
                'company_name' => $company->name,
                'operation_id' => $operation->id,
                'operation_name' => $operation->name,
            ],
            'expires_at' => now()->addDay(),
        ]);

        $result = app(ReceiptConfirmationService::class)->confirmDraft($draft);

        $this->assertTrue($result['ok']);
        $this->assertDatabaseHas('transactions', [
            'workspace_id' => $this->workspace->id,
            'category_id' => $category->id,
            'company_id' => $company->id,
            'operation_id' => $operation->id,
            'status' => TransactionStatus::Confirmed->value,
            'amount' => 1941.14,
        ]);
    }
}
