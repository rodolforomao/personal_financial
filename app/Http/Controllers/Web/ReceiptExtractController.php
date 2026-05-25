<?php

namespace App\Http\Controllers\Web;

use App\Application\Services\ReceiptFormPrefillService;
use App\Core\Support\BrazilianAmountParser;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Modules\Finance\Infrastructure\Models\Transaction;
use Modules\OCR\Application\Services\ReceiptStorageService;
use Modules\OCR\Infrastructure\Models\Document;

class ReceiptExtractController extends Controller
{
    public function preview(Request $request, ReceiptFormPrefillService $prefill): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        $workspaceId = (int) $request->attributes->get('workspace_id');
        $file = $request->file('file');
        $mime = $file->getMimeType() ?: 'application/octet-stream';

        if (str_contains($mime, 'pdf') && empty(config('financial.ai.openai.api_key'))) {
            return response()->json([
                'ok' => false,
                'message' => 'PDF exige IA ativa. Envie JPG/PNG ou ative a IA em Configuração IA.',
            ], 422);
        }

        $tmp = $file->store('tmp/receipt-scan', 'local');
        $fullPath = Storage::disk('local')->path($tmp);

        try {
            $extracted = $prefill->extractFromUpload($fullPath, $mime, $workspaceId, $file->getClientOriginalName());
            $data = $extracted['form'];
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($tmp);

            return response()->json([
                'ok' => false,
                'message' => 'Não foi possível ler o arquivo. Tente uma imagem mais nítida.',
            ], 422);
        }

        Storage::disk('local')->delete($tmp);

        if (($data['amount'] ?? 0) <= 0) {
            return response()->json([
                'ok' => true,
                'data' => $data,
                'warning' => 'Valor não identificado — revise antes de salvar.',
            ]);
        }

        return response()->json(['ok' => true, 'data' => $data]);
    }

    public function previewDocument(
        Request $request,
        Document $document,
        ReceiptFormPrefillService $prefill,
    ): JsonResponse {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        abort_unless($document->workspace_id === $workspaceId, 404);

        $fullPath = Storage::disk('local')->path($document->storage_path);
        if (! is_file($fullPath)) {
            return response()->json(['ok' => false, 'message' => 'Arquivo não encontrado.'], 404);
        }

        try {
            $force = $request->boolean('reprocess') || $this->ocrResultLooksStale($document);
            if (! $force && $document->ocr_result && is_array($document->ocr_result) && ! empty($document->ocr_result['amount'])) {
                $data = $prefill->mapForForm($document->ocr_result, $workspaceId);
            } else {
                $extracted = $prefill->extractFromUpload(
                    $fullPath,
                    $document->mime_type,
                    $workspaceId,
                    $document->original_name,
                );
                $data = $extracted['form'];
                $document->update([
                    'ocr_result' => $extracted['raw'],
                    'status' => 'processed',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('document extract failed', [
                'document_id' => $document->id,
                'mime' => $document->mime_type,
                'error' => $e->getMessage(),
            ]);

            $message = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Falha ao ler o comprovante.';

            if (str_contains($e->getMessage(), 'Invalid MIME type')) {
                $message = 'PDF não pôde ser enviado à IA. Atualize o sistema ou envie uma imagem (JPG/PNG).';
            }

            return response()->json([
                'ok' => false,
                'message' => $message,
            ], 422);
        }

        return response()->json(['ok' => true, 'data' => $data, 'document_id' => $document->id]);
    }

    public function storeAndPrefill(
        Request $request,
        ReceiptFormPrefillService $prefill,
        ReceiptStorageService $storage,
    ): JsonResponse {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'transaction_id' => 'nullable|integer|exists:transactions,id',
        ]);

        $workspaceId = (int) $request->attributes->get('workspace_id');
        $transactionId = $request->integer('transaction_id') ?: null;

        if ($transactionId) {
            $tx = Transaction::query()->findOrFail($transactionId);
            abort_unless($tx->workspace_id === $workspaceId, 404);
            $this->authorize('update', $tx);
        }

        $document = $storage->store($workspaceId, $request->user(), $request->file('file'), $transactionId, queueOcr: false);

        $fullPath = Storage::disk('local')->path($document->storage_path);

        try {
            $extracted = $prefill->extractFromUpload(
                $fullPath,
                $document->mime_type,
                $workspaceId,
                $document->original_name,
            );
            $data = $extracted['form'];
            $document->update([
                'ocr_result' => $extracted['raw'],
                'status' => 'processed',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Arquivo salvo, mas a leitura falhou. Preencha manualmente.',
                'document_id' => $document->id,
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'data' => $data,
            'document_id' => $document->id,
        ]);
    }

    protected function ocrResultLooksStale(Document $document): bool
    {
        $ocr = $document->ocr_result;
        if (! is_array($ocr) || empty($ocr['raw_text'])) {
            return false;
        }

        $parser = app(BrazilianAmountParser::class);
        $hint = $parser->hintFromFilename($document->original_name);
        $best = $parser->extractBestFromText((string) $ocr['raw_text'], $hint);
        $stored = (float) ($ocr['amount'] ?? 0);

        return $best !== null && abs($best - $stored) > 0.01;
    }
}
