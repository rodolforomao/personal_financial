<?php

namespace Modules\OCR\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\OCR\Application\Services\ReceiptStorageService;
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

    public function store(Request $request, ReceiptStorageService $storage): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,xml,csv,ofx|max:10240',
            'document_type' => 'nullable|in:receipt,invoice,statement,boleto,other',
        ]);

        $document = $storage->store(
            (int) $request->attributes->get('workspace_id'),
            $request->user(),
            $request->file('file'),
        );

        if ($request->filled('document_type')) {
            $document->update(['document_type' => $request->input('document_type')]);
        }

        return response()->json($document->fresh(), 201);
    }

    public function show(Document $document): JsonResponse
    {
        return response()->json($document->load('ocrJobs'));
    }
}
