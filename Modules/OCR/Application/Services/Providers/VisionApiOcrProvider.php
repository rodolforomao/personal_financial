<?php

namespace Modules\OCR\Application\Services\Providers;

use App\Core\Contracts\OcrProviderInterface;
use App\Core\DTOs\OcrRequestData;
use App\Core\DTOs\OcrResultData;
use App\Core\Support\MarkdownJsonParser;
use Illuminate\Support\Facades\Http;

class VisionApiOcrProvider implements OcrProviderInterface
{
    public function name(): string
    {
        return 'vision';
    }

    public function extract(OcrRequestData $request): OcrResultData
    {
        $base64 = base64_encode(file_get_contents($request->filePath));

        $baseUrl = config('financial.ai.openai.base_url');
        $response = Http::withToken(config('financial.ai.openai.api_key'))
            ->post("{$baseUrl}/chat/completions", [
                'model' => config('financial.ai.openai.model', 'gpt-4o-mini'),
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $this->prompt()],
                        ['type' => 'image_url', 'image_url' => ['url' => "data:{$request->mimeType};base64,{$base64}"]],
                    ],
                ]],
                'temperature' => 0.1,
            ])
            ->throw()
            ->json();

        $content = $response['choices'][0]['message']['content'] ?? '{}';
        $parsed = MarkdownJsonParser::decode($content);

        $amount = isset($parsed['amount']) ? (float) $parsed['amount'] : null;

        return new OcrResultData(
            rawText: $parsed['raw_text'] ?? $content,
            entities: $parsed,
            amount: $amount,
            date: $parsed['date'] ?? null,
            counterparty: $parsed['counterparty'] ?? null,
            bank: $parsed['bank'] ?? null,
            suggestedCategory: $parsed['suggested_category'] ?? null,
            confidence: 92.0,
        );
    }

    protected function prompt(): string
    {
        return <<<'PROMPT'
Analise comprovantes financeiros brasileiros (PIX, TED, boleto, Nubank, Inter, Itaú) e também faturas/invoices em USD ou USDT.

Retorne APENAS um JSON válido (sem markdown), com:
- transfer_direction: "received" se o titular RECEBEU o valor; "sent" se ENVIOU — para invoices/faturas use "sent"
- type: "income" se transfer_direction=received; "expense" se transfer_direction=sent
- currency: "BRL" para reais, "USD" para dólares ou USDT (se o documento tiver valores em USD, $, USDT)
- amount: valor TOTAL do documento no campo "currency" informado, com ponto decimal (ex.: 42.22 para $42.22 USD; 4000.00 para R$ 4.000,00)
- date: YYYY-MM-DD
- description: texto curto do comprovante (ex.: "Pix recebido", "Invoice UltaHost")
- counterparty: nome da outra parte — para invoice, é o nome da empresa emissora
- bank: instituição do comprovante ou plataforma de cobrança (ex.: "Nubank", "Stripe")
- receipt_type: pix_received, pix_sent, boleto, transfer, card, bank_receipt, invoice
- suggested_category: slug em minúsculas (ex.: "tecnologia", "hospedagem", "servicos")
- raw_text: linhas legíveis do documento

Em comprovantes Nubank/Inter com blocos Destino e Origem: quem está em Destino recebeu o Pix (transfer_direction=received).
Se o documento tiver valores em USD/USDT, defina currency="USD" e amount com o total em USD.
PROMPT;
    }
}
