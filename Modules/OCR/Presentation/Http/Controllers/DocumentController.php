<?php

namespace Modules\OCR\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\OCR\Application\Services\OcrService;
use Modules\OCR\Infrastructure\Models\Document;

class DocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Document::query()
                ->where('workspace_id', $request->attributes->get('workspace_id'))
                ->latest()
                ->paginate(20)
        );
    }

    public function store(Request $request, OcrService $ocrService): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,csv,ofx|max:10240',
            'document_type' => 'nullable|in:receipt,invoice,statement,boleto,other',
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
            'document_type' => $request->input('document_type', 'receipt'),
            'status' => 'pending',
        ]);

        $ocrService->queueDocument($document);

        return response()->json($document, 201);
    }

    public function show(Document $document): JsonResponse
    {
        return response()->json($document->load('ocrJobs'));
    }
}
