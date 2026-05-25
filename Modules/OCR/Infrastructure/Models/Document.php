<?php

namespace Modules\OCR\Infrastructure\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Infrastructure\Models\Workspace;
use Modules\Finance\Infrastructure\Models\Transaction;

class Document extends Model
{
    protected $fillable = [
        'workspace_id', 'user_id', 'original_name', 'storage_path', 'content_hash',
        'mime_type', 'size', 'document_type', 'status', 'ocr_result', 'transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'ocr_result' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function ocrJobs(): HasMany
    {
        return $this->hasMany(OcrJob::class);
    }
}
