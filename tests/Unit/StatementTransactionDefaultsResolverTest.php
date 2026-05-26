<?php

namespace Tests\Unit;

use App\Core\Enums\FundingSource;
use App\Core\Enums\PaymentMethod;
use App\Core\Enums\TransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Categorization\Infrastructure\Models\CategorizationRule;
use Modules\Finance\Application\Services\StatementImportService;
use Modules\Finance\Application\Services\StatementReconciliationService;
use Modules\Finance\Application\Services\StatementTransactionDefaultsResolver;
use Modules\Finance\Infrastructure\Models\StatementImport;
use Modules\Finance\Infrastructure\Models\StatementLine;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class StatementTransactionDefaultsResolverTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();

        Category::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Transporte',
            'slug' => 'transporte',
            'type' => 'expense',
            'is_system' => true,
        ]);
    }

    public function test_inter_uber_line_gets_transporte_card_and_inter(): void
    {
        $import = StatementImport::query()->create([
            'workspace_id' => $this->workspace->id,
            'original_name' => 'inter.ofx',
            'format' => 'ofx',
            'status' => StatementImport::STATUS_PENDING,
            'settings' => ['bank_slug' => 'inter'],
        ]);

        $line = StatementLine::query()->create([
            'statement_import_id' => $import->id,
            'transaction_date' => '2026-05-10',
            'amount' => 43.97,
            'type' => TransactionType::Expense,
            'description' => 'Compra no debito: "No estabelecimento Uber UBER *TRIP HELP.U SP BRA"',
            'counterparty' => 'Uber Uber *trip Help.u Sp Bra',
            'match_status' => StatementLine::STATUS_UNMATCHED,
        ]);

        $defaults = app(StatementTransactionDefaultsResolver::class)->resolve($import, $line);

        $this->assertSame('transporte', Category::query()->find($defaults['category_id'])?->slug);
        $this->assertSame(FundingSource::Inter->value, $defaults['funding_source']);
        $this->assertSame(PaymentMethod::Card->value, $defaults['payment_method']);
    }

    public function test_inter_pix_uses_pix_not_card(): void
    {
        $import = StatementImport::query()->create([
            'workspace_id' => $this->workspace->id,
            'original_name' => 'inter.ofx',
            'format' => 'ofx',
            'status' => StatementImport::STATUS_PENDING,
            'settings' => ['bank_slug' => 'inter'],
        ]);

        $line = StatementLine::query()->create([
            'statement_import_id' => $import->id,
            'transaction_date' => '2026-05-08',
            'amount' => 150,
            'type' => TransactionType::Expense,
            'description' => 'Pix enviado: "Cp :18236120-Rafael Silva Oliveira"',
            'counterparty' => 'Rafael Silva Oliveira',
            'match_status' => StatementLine::STATUS_UNMATCHED,
        ]);

        $defaults = app(StatementTransactionDefaultsResolver::class)->resolve($import, $line);

        $this->assertNull($defaults['category_id']);
        $this->assertSame(FundingSource::Inter->value, $defaults['funding_source']);
        $this->assertSame(PaymentMethod::Pix->value, $defaults['payment_method']);
    }

    public function test_import_ofx_inter_fixture_applies_defaults_on_transaction(): void
    {
        $import = app(StatementImportService::class)->parseOfx(
            $this->workspace->id,
            $this->user,
            base_path('tests/fixtures/inter-uber.ofx'),
            'inter-uber.ofx',
        );

        $this->assertSame('inter', $import->settings['bank_slug'] ?? null);

        $uberLine = $import->lines()
            ->visible()
            ->get()
            ->first(fn ($l) => str_contains(strtolower($l->description), 'uber') && (float) $l->amount === 43.97);

        $tx = app(StatementReconciliationService::class)->importAsTransaction($uberLine, $this->workspace->id);

        $this->assertSame('transporte', $tx->category?->slug);
        $this->assertSame(FundingSource::Inter->value, $tx->funding_source);
        $this->assertSame(PaymentMethod::Card->value, $tx->payment_method);
    }

    public function test_statement_line_description_matches_contains_rule_without_space(): void
    {
        $category = Category::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Eventos',
            'slug' => 'eventos',
            'type' => 'expense',
            'is_system' => false,
        ]);
        CategorizationRule::query()->create([
            'workspace_id' => $this->workspace->id,
            'category_id' => $category->id,
            'name' => 'Festival',
            'pattern' => 'festival',
            'match_type' => 'contains',
            'transaction_type' => 'expense',
            'priority' => 10,
            'is_active' => true,
        ]);
        $import = StatementImport::query()->create([
            'workspace_id' => $this->workspace->id,
            'original_name' => 'nubank.ofx',
            'format' => 'ofx',
            'status' => StatementImport::STATUS_PENDING,
        ]);
        $line = StatementLine::query()->create([
            'statement_import_id' => $import->id,
            'transaction_date' => '2026-05-03',
            'amount' => 550,
            'type' => TransactionType::Expense,
            'description' => 'Festivalmicare2026',
            'match_status' => StatementLine::STATUS_UNMATCHED,
        ]);

        $defaults = app(StatementTransactionDefaultsResolver::class)->resolve($import, $line);
        $tx = app(StatementReconciliationService::class)->importAsTransaction($line, $this->workspace->id);

        $this->assertSame($category->id, $defaults['category_id']);
        $this->assertSame('eventos', $tx->category?->slug);
        $this->assertSame('statement_import', $tx->source);
        $this->assertSame('ofx', $tx->metadata['statement_format'] ?? null);
    }
}
