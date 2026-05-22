<?php

namespace Modules\OCR\Application\Services\Providers;

use App\Core\Contracts\OcrProviderInterface;
use App\Core\DTOs\OcrRequestData;
use App\Core\DTOs\OcrResultData;
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
                        ['type' => 'text', 'text' => 'Extract financial data from this document. Return JSON: amount, date, counterparty, bank, suggested_category, raw_text'],
                        ['type' => 'image_url', 'image_url' => ['url' => "data:{$request->mimeType};base64,{$base64}"]],
                    ],
                ]],
            ])
            ->throw()
            ->json();

        $content = $response['choices'][0]['message']['content'] ?? '{}';
        $parsed = json_decode($content, true) ?? [];

        return new OcrResultData(
            rawText: $parsed['raw_text'] ?? $content,
            entities: $parsed,
            amount: $parsed['amount'] ?? null,
            date: $parsed['date'] ?? null,
            counterparty: $parsed['counterparty'] ?? null,
            bank: $parsed['bank'] ?? null,
            suggestedCategory: $parsed['suggested_category'] ?? null,
            confidence: 90.0,
        );
    }
}
