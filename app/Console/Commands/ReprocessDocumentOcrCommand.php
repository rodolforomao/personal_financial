<?php

namespace App\Console\Commands;

use App\Application\Services\ReceiptFormPrefillService;
use Illuminate\Console\Command;
use Modules\OCR\Infrastructure\Models\Document;

class ReprocessDocumentOcrCommand extends Command
{
    protected $signature = 'documents:reprocess-ocr {document : ID do documento}';

    protected $description = 'Reextrai dados do comprovante (valor, banco, tipo) com o algoritmo atualizado';

    public function handle(ReceiptFormPrefillService $prefill): int
    {
        $document = Document::query()->findOrFail($this->argument('document'));
        $path = storage_path('app/'.$document->storage_path);

        if (! is_file($path)) {
            $this->error("Arquivo não encontrado: {$document->storage_path}");

            return self::FAILURE;
        }

        $result = $prefill->extractFromUpload(
            $path,
            $document->mime_type,
            $document->workspace_id,
            $document->original_name,
        );

        $document->update([
            'ocr_result' => $result['raw'],
            'status' => 'processed',
        ]);

        $raw = $result['raw'];
        $this->info('Comprovante: '.($raw['receipt_type_label'] ?? '—'));
        $this->info('Banco: '.($raw['bank'] ?? '—'));
        $this->info('Valor: R$ '.number_format((float) ($raw['amount'] ?? 0), 2, ',', '.'));
        $this->info('Descrição: '.($raw['description'] ?? '—'));

        return self::SUCCESS;
    }
}
