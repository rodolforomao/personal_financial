<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Modules\Finance\Infrastructure\Models\Transaction;
use Modules\OCR\Application\Services\ReceiptStorageService;
use Modules\OCR\Infrastructure\Models\Document;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        return view('documents.index', [
            'documents' => Document::query()
                ->where('workspace_id', $request->attributes->get('workspace_id'))
                ->with('transaction')
                ->latest()
                ->paginate(15),
            'requirePassword' => config('financial.security.require_password_for_transaction_sensitive_edit', true),
        ]);
    }

    public function store(Request $request, ReceiptStorageService $storage): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        $storage->store(
            (int) $request->attributes->get('workspace_id'),
            $request->user(),
            $request->file('file'),
        );

        return back()->with('success', 'Documento enviado para fila OCR.');
    }

    public function show(Request $request, Document $document): StreamedResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        abort_unless($document->workspace_id === $workspaceId, 404);
        abort_unless(Storage::disk('local')->exists($document->storage_path), 404);

        return Storage::disk('local')->response(
            $document->storage_path,
            $document->original_name,
            ['Content-Type' => $document->mime_type]
        );
    }

    public function destroy(Request $request, Document $document, ReceiptStorageService $storage): RedirectResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        abort_unless($document->workspace_id === $workspaceId, 404);

        if ($document->transaction_id) {
            $transaction = Transaction::query()
                ->where('workspace_id', $workspaceId)
                ->findOrFail($document->transaction_id);

            $this->authorize('update', $transaction);

            if (config('financial.security.require_password_for_transaction_sensitive_edit', true)) {
                $request->validate([
                    'current_password' => ['required', 'current_password'],
                ], [
                    'current_password.required' => 'Informe sua senha para remover o comprovante vinculado.',
                ]);
            }
        }

        $storage->deleteFile($document);
        $document->delete();

        return back()->with('success', 'Documento excluído.');
    }
}
