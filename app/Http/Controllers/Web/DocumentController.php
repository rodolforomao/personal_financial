<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\OCR\Application\Services\OcrService;
use Modules\OCR\Infrastructure\Models\Document;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        return view('documents.index', [
            'documents' => Document::query()
                ->where('workspace_id', $request->attributes->get('workspace_id'))
                ->latest()
                ->paginate(15),
        ]);
    }

    public function store(Request $request, OcrService $ocrService): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $file = $request->file('file');
        $path = $file->store('documents/'.$request->attributes->get('workspace_id'), 'local');

        $document = Document::query()->create([
            'workspace_id' => $request->attributes->get('workspace_id'),
            'user_id' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'status' => 'pending',
        ]);

        $ocrService->queueDocument($document);

        return back()->with('success', 'Documento enviado para fila OCR.');
    }
}
