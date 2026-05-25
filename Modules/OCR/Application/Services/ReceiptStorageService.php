<?php

namespace Modules\OCR\Application\Services;

use App\Core\Support\DocumentContentHasher;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\OCR\Infrastructure\Models\Document;

class ReceiptStorageService
{
    public function __construct(
        private readonly OcrService $ocrService,
        private readonly DocumentContentHasher $contentHasher,
    ) {}

    public function store(
        int $workspaceId,
        User $user,
        UploadedFile $file,
        ?int $transactionId = null,
        bool $queueOcr = true,
    ): Document {
        $contentHash = $this->contentHasher->hashUpload($file);
        $existingPath = $this->findExistingStoragePath($workspaceId, $contentHash);

        if ($existingPath !== null) {
            $path = $existingPath;
            $size = Storage::disk('local')->size($path);
        } else {
            $path = $file->store('documents/'.$workspaceId, 'local');
            $size = $file->getSize();
        }

        $document = Document::query()->create([
            'workspace_id' => $workspaceId,
            'user_id' => $user->id,
            'original_name' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'content_hash' => $contentHash,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $size,
            'document_type' => 'receipt',
            'status' => 'pending',
            'transaction_id' => $transactionId,
        ]);

        if ($queueOcr) {
            $this->ocrService->queueDocument($document);
        }

        return $document;
    }

    /**
     * Copia comprovante salvo no fluxo Telegram/WhatsApp para documents/ e vincula à transação.
     *
     * @param  array<string, mixed>|null  $ocrResult
     */
    public function attachFromInboundPath(
        int $workspaceId,
        User $user,
        string $inboundRelativePath,
        string $mimeType,
        string $originalName,
        int $transactionId,
        ?array $ocrResult = null,
        string $documentType = 'receipt',
    ): ?Document {
        if (! Storage::disk('local')->exists($inboundRelativePath)) {
            return null;
        }

        $fullPath = Storage::disk('local')->path($inboundRelativePath);
        $contentHash = $this->contentHasher->hashFile($fullPath, $mimeType);
        $existingPath = $this->findExistingStoragePath($workspaceId, $contentHash);

        if ($existingPath !== null) {
            $dest = $existingPath;
            $size = Storage::disk('local')->size($dest);
        } else {
            $ext = match (true) {
                str_contains($mimeType, 'xml') => 'xml',
                str_contains($mimeType, 'pdf') => 'pdf',
                str_contains($mimeType, 'png') => 'png',
                str_contains($mimeType, 'webp') => 'webp',
                default => 'jpg',
            };

            $dest = 'documents/'.$workspaceId.'/'.Str::uuid().'.'.$ext;
            Storage::disk('local')->copy($inboundRelativePath, $dest);
            $size = Storage::disk('local')->size($dest);
        }

        return Document::query()->create([
            'workspace_id' => $workspaceId,
            'user_id' => $user->id,
            'original_name' => $originalName,
            'storage_path' => $dest,
            'content_hash' => $contentHash,
            'mime_type' => $mimeType,
            'size' => $size,
            'document_type' => $documentType,
            'status' => $ocrResult ? 'processed' : 'pending',
            'ocr_result' => $ocrResult,
            'transaction_id' => $transactionId,
        ]);
    }

    public function deleteFile(Document $document): void
    {
        $path = $document->storage_path;
        if (! $path) {
            return;
        }

        $stillReferenced = Document::query()
            ->where('storage_path', $path)
            ->where('id', '!=', $document->id)
            ->exists();

        if (! $stillReferenced && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    private function findExistingStoragePath(int $workspaceId, string $contentHash): ?string
    {
        $path = Document::query()
            ->where('workspace_id', $workspaceId)
            ->where('content_hash', $contentHash)
            ->whereNotNull('storage_path')
            ->value('storage_path');

        if ($path && Storage::disk('local')->exists($path)) {
            return $path;
        }

        return null;
    }
}
