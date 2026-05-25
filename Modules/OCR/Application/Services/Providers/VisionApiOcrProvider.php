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
Analise comprovantes financeiros brasileiros (PIX, TED, boleto, Nubank, Inter, Itaú).

Retorne APENAS um JSON válido (sem markdown), com:
- transfer_direction: "received" se o titular do comprovante RECEBEU o valor (aparece em Destino / "você recebeu" / crédito); "sent" se ENVIOU (Origem / "Pix enviado" / "você enviou" / débito)
- type: "income" se transfer_direction=received; "expense" se transfer_direction=sent
- amount: número em reais com ponto decimal (ex.: 4000.00 para R$ 4.000,00)
- date: YYYY-MM-DD
- description: texto curto do comprovante (ex.: "Pix recebido", "Pix enviado")
- counterparty: nome da OUTRA parte (quem enviou se você recebeu; quem recebeu se você enviou) — nunca repita o titular do comprovante
- bank: instituição do comprovante (ex.: "Nubank")
- receipt_type: pix_received, pix_sent, boleto, transfer, card, bank_receipt
- suggested_category: slug em minúsculas
- raw_text: linhas legíveis incluindo Destino e Origem quando existirem

Em comprovantes Nubank/Inter com blocos Destino e Origem: quem está em Destino recebeu o Pix (transfer_direction=received).
PROMPT;
    }
}
