<?php

namespace Modules\OCR\Application\Services\Providers;

use App\Core\Contracts\OcrProviderInterface;
use App\Core\DTOs\OcrRequestData;
use App\Core\DTOs\OcrResultData;
use App\Core\Support\BrazilianAmountParser;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Modules\Integrations\Application\Services\ReceiptClassifier;

class TesseractOcrProvider implements OcrProviderInterface
{
    public function name(): string
    {
        return 'tesseract';
    }

    public function extract(OcrRequestData $request): OcrResultData
    {
        $outputFile = sys_get_temp_dir().'/ocr_'.uniqid().'.txt';

        Process::run([
            config('financial.ocr.tesseract.binary', 'tesseract'),
            $request->filePath,
            $outputFile,
            '-l', config('financial.ocr.tesseract.lang', 'por'),
        ])->throw();

        $rawText = file_get_contents($outputFile.'.txt') ?: '';

        return $this->parseEntities($rawText);
    }

    protected function parseEntities(string $text): OcrResultData
    {
        $parser = app(BrazilianAmountParser::class);
        $amount = $parser->extractBestFromText($text);

        $date = null;
        if (preg_match('/\d{2}\/\d{2}\/\d{4}/', $text, $m)) {
            $date = $m[0];
        }

        $classification = app(ReceiptClassifier::class)->classify($text);

        return new OcrResultData(
            rawText: $text,
            entities: [
                'lines' => explode("\n", trim($text)),
                'bank' => $classification['bank'],
                'receipt_type' => $classification['receipt_type'],
            ],
            amount: $amount,
            date: $date,
            counterparty: null,
            bank: $classification['bank'],
            confidence: $text ? 70.0 : 0.0,
            suggestedCategory: $this->guessCategory($text),
        );
    }

    protected function guessCategory(string $text): ?string
    {
        $lower = Str::lower($text);
        $patterns = config('financial.default_categorization_patterns', []);

        foreach ($patterns as $pattern => $slug) {
            if (str_contains($lower, Str::lower($pattern))) {
                return $slug;
            }
        }

        return null;
    }
}
