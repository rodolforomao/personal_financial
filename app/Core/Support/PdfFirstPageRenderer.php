<?php

namespace App\Core\Support;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class PdfFirstPageRenderer
{
    /**
     * Render the first page of a PDF to a temporary PNG file.
     *
     * @return string Absolute path to the PNG (caller should delete when done)
     */
    public function toPng(string $pdfPath): string
    {
        if (! is_file($pdfPath)) {
            throw new RuntimeException('Arquivo PDF não encontrado.');
        }

        $prefix = sys_get_temp_dir().'/receipt_pdf_'.uniqid('', true);

        $result = Process::timeout(90)->run([
            config('financial.ocr.pdf.pdftoppm_binary', 'pdftoppm'),
            '-png',
            '-f', '1',
            '-l', '1',
            '-singlefile',
            $pdfPath,
            $prefix,
        ]);

        $pngPath = $prefix.'.png';

        if (! $result->successful() || ! is_file($pngPath)) {
            throw new RuntimeException(
                'Não foi possível converter o PDF para imagem. Verifique se poppler-utils (pdftoppm) está instalado.'
            );
        }

        return $pngPath;
    }
}
