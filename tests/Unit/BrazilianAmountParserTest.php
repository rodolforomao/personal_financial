<?php

namespace Tests\Unit;

use App\Core\Support\BrazilianAmountParser;
use PHPUnit\Framework\TestCase;

class BrazilianAmountParserTest extends TestCase
{
    private BrazilianAmountParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new BrazilianAmountParser;
    }

    public function test_parses_brazilian_thousands_format(): void
    {
        $this->assertSame(5000.0, $this->parser->parse('5.000,00'));
        $this->assertSame(5000.0, $this->parser->parse('R$ 5.000,00'));
    }

    public function test_parses_decimal_with_dot(): void
    {
        $this->assertSame(5000.0, $this->parser->parse('5000.00'));
    }

    public function test_does_not_truncate_5000_to_500_in_json_text(): void
    {
        $text = '{"amount": 5000.00, "raw_text": "Pix enviado R$ 5.000,00"}';
        $this->assertSame(5000.0, $this->parser->extractBestFromText($text));
    }

    public function test_prefers_labeled_amount_over_partial_match(): void
    {
        $text = 'Pix enviado R$ 5.000,00 Sobre a transação taxa R$ 5,00';
        $this->assertSame(5000.0, $this->parser->extractBestFromText($text));
    }

    public function test_filename_5k_hint(): void
    {
        $this->assertSame(5000.0, $this->parser->hintFromFilename('Multifilmes - Rodolfo 5k.jpeg'));
    }

    public function test_filename_hint_resolves_ambiguous_json(): void
    {
        $text = '"amount": 5000.00';
        $hint = $this->parser->hintFromFilename('Rodolfo 5k.jpeg');
        $this->assertSame(5000.0, $this->parser->extractBestFromText($text, $hint));
    }

    public function test_extracts_usd_total_from_invoice_text(): void
    {
        $text = "Sub Total \$42.22USD\nTotal \$42.22USD";
        $this->assertSame(42.22, $this->parser->extractUsdAmount($text));
    }

    public function test_extracts_usd_amount_from_dollar_sign(): void
    {
        $text = 'Payment $1.23USD  Invoice total $42.22USD';
        $this->assertSame(42.22, $this->parser->extractUsdAmount($text));
    }

    public function test_extracts_usd_from_plain_usd_suffix(): void
    {
        $this->assertSame(42.22, $this->parser->extractUsdAmount('42.22USD'));
    }
}
