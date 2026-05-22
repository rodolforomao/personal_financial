<?php

namespace Modules\OCR\Application\Services\Providers;

use App\Core\Contracts\OcrProviderInterface;
use App\Core\DTOs\OcrRequestData;
use App\Core\DTOs\OcrResultData;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

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
        $amount = null;
        if (preg_match('/R\$\s*([\d.,]+)/i', $text, $m)) {
            $amount = (float) str_replace(['.', ','], ['', '.'], $m[1]);
        }

        $date = null;
        if (preg_match('/\d{2}\/\d{2}\/\d{4}/', $text, $m)) {
            $date = $m[0];
        }

        return new OcrResultData(
            rawText: $text,
            entities: ['lines' => explode("\n", trim($text))],
            amount: $amount,
            date: $date,
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
