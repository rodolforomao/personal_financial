<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Finance\Infrastructure\Models\Transaction;
use Modules\OCR\Application\Services\ReceiptStorageService;
use Modules\OCR\Infrastructure\Models\Document;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionReceiptController extends Controller
{
    public function store(Request $request, Transaction $transaction, ReceiptStorageService $storage): RedirectResponse
    {
        $this->authorize('update', $transaction);

        $request->validate([
            'files' => 'required|array|min:1|max:10',
            'files.*' => 'file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        $workspaceId = (int) $request->attributes->get('workspace_id');
        abort_unless($transaction->workspace_id === $workspaceId, 404);

        foreach ($request->file('files', []) as $file) {
            $storage->store($workspaceId, $request->user(), $file, $transaction->id);
        }

        $count = count($request->file('files', []));

        return redirect()
            ->route('transactions.edit', $transaction)
            ->with('success', $count === 1 ? 'Comprovante anexado.' : "{$count} comprovantes anexados.");
    }

    public function link(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $transaction);

        $workspaceId = (int) $request->attributes->get('workspace_id');
        abort_unless($transaction->workspace_id === $workspaceId, 404);

        $validated = $request->validate([
            'document_id' => 'required|integer|exists:documents,id',
        ]);

        $document = Document::query()
            ->where('workspace_id', $workspaceId)
            ->whereNull('transaction_id')
            ->findOrFail($validated['document_id']);

        $document->update(['transaction_id' => $transaction->id]);

        return redirect()
            ->route('transactions.edit', $transaction)
            ->with('success', 'Comprovante vinculado à transação.');
    }

    public function show(Request $request, Transaction $transaction, Document $document): StreamedResponse
    {
        $this->authorize('view', $transaction);
        $this->assertReceiptBelongsToTransaction($transaction, $document, $request);

        abort_unless(Storage::disk('local')->exists($document->storage_path), 404);

        return Storage::disk('local')->response(
            $document->storage_path,
            $document->original_name,
            ['Content-Type' => $document->mime_type]
        );
    }

    public function destroy(Request $request, Transaction $transaction, Document $document, ReceiptStorageService $storage): RedirectResponse
    {
        $this->authorize('update', $transaction);
        $this->assertReceiptBelongsToTransaction($transaction, $document, $request);

        $storage->deleteFile($document);
        $document->delete();

        return redirect()
            ->route('transactions.edit', $transaction)
            ->with('success', 'Comprovante removido.');
    }

    protected function assertReceiptBelongsToTransaction(Transaction $transaction, Document $document, Request $request): void
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        abort_unless(
            $transaction->workspace_id === $workspaceId
            && $document->workspace_id === $workspaceId
            && (int) $document->transaction_id === (int) $transaction->id,
            404
        );
    }
}
