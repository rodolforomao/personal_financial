<?php

namespace Tests\Unit;

use Modules\Integrations\Application\Services\ReceiptDraftSupplementParser;
use PHPUnit\Framework\TestCase;

class ReceiptDraftSupplementParserTest extends TestCase
{
    public function test_parses_labeled_fields_from_user_example(): void
    {
        $parser = new ReceiptDraftSupplementParser;

        $fields = $parser->parse(
            'Contraparte: presente da shilene. Categoria: shilene. Empresa pessoal, operação geral.',
        );

        $this->assertSame('presente da shilene', $fields['counterparty']);
        $this->assertSame('shilene', $fields['category']);
        $this->assertSame('pessoal', $fields['company']);
        $this->assertSame('geral', $fields['operation']);
    }

    public function test_parses_colon_separated_empresa_and_operacao(): void
    {
        $parser = new ReceiptDraftSupplementParser;

        $fields = $parser->parse('Empresa: Pessoal. Operação: Geral.');

        $this->assertSame('Pessoal', $fields['company']);
        $this->assertSame('Geral', $fields['operation']);
    }

    public function test_parses_combined_categoria_and_operacao_without_colon(): void
    {
        $parser = new ReceiptDraftSupplementParser;

        $fields = $parser->parse('Categoria e operacao Oec empreendimentos');

        $this->assertSame('Oec empreendimentos', $fields['category']);
        $this->assertSame('Oec empreendimentos', $fields['operation']);
    }

    public function test_parses_payment_context_without_colon(): void
    {
        $parser = new ReceiptDraftSupplementParser;

        $fields = $parser->parse('Fonte Nubank. Meio de pagamento Pix. Contraparte ENTERNET P LTDA - ME.');

        $this->assertSame('Nubank', $fields['funding_source']);
        $this->assertSame('Pix', $fields['payment_method']);
        $this->assertSame('ENTERNET P LTDA - ME', $fields['counterparty']);
    }
}
