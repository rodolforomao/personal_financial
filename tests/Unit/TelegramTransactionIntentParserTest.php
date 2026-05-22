<?php

namespace Tests\Unit;

use App\Core\Enums\TransactionType;
use Modules\Integrations\Application\Services\TelegramTransactionIntentParser;
use PHPUnit\Framework\TestCase;

class TelegramTransactionIntentParserTest extends TestCase
{
    public function test_parses_expense_with_thousands_separator(): void
    {
        $parser = new TelegramTransactionIntentParser;
        $result = $parser->parse(
            'Adicionar na minha conta um gasto de 16.000 aportando sociedade da multfilmes de gyn'
        );

        $this->assertNotNull($result);
        $this->assertSame(TransactionType::Expense, $result['type']);
        $this->assertSame(16000.0, $result['amount']);
        $this->assertStringContainsString('multfilmes', mb_strtolower($result['description']));
    }

    public function test_parses_income(): void
    {
        $parser = new TelegramTransactionIntentParser;
        $result = $parser->parse('Receita de 5.000 consultoria maio');

        $this->assertNotNull($result);
        $this->assertSame(TransactionType::Income, $result['type']);
        $this->assertSame(5000.0, $result['amount']);
    }

    public function test_returns_null_without_financial_intent(): void
    {
        $parser = new TelegramTransactionIntentParser;
        $this->assertNull($parser->parse('Teste Financial IQ — Telegram configurado com sucesso!'));
    }
}
