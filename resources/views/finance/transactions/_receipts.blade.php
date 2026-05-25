<div class="card card-outline card-secondary mt-3">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h3 class="card-title mb-0">
            <i class="bi bi-paperclip"></i> Comprovantes
            <span class="badge text-bg-light ms-1">{{ $transaction->documents->count() }}</span>
        </h3>
        <a href="{{ route('transactions.index') }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-list-ul"></i> Ver todas as transações
        </a>
    </div>
    <div class="card-body">
        @if($transaction->documents->isNotEmpty())
            <ul class="list-group list-group-flush mb-3">
                @foreach($transaction->documents as $doc)
                    <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2 px-0">
                        <div class="flex-grow-1">
                            <a href="{{ route('transactions.receipts.show', [$transaction, $doc]) }}" target="_blank" class="fw-semibold text-decoration-none">
                                <i class="bi bi-file-earmark"></i> {{ $doc->original_name }}
                            </a>
                            <div class="small text-muted">
                                {{ number_format($doc->size / 1024, 1) }} KB
                                · {{ $doc->created_at->format('d/m/Y H:i') }}
                                · <span class="badge text-bg-secondary">{{ $doc->status }}</span>
                                @if($doc->ocr_result && isset($doc->ocr_result['amount']))
                                    · OCR: R$ {{ number_format((float) $doc->ocr_result['amount'], 2, ',', '.') }}
                                @endif
                            </div>
                        </div>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-outline-info btn-apply-receipt"
                                data-document-id="{{ $doc->id }}"
                                data-url="{{ route('documents.extract', $doc) }}">
                                <i class="bi bi-magic"></i> Aplicar na transação
                            </button>
                            <form action="{{ route('transactions.receipts.destroy', [$transaction, $doc]) }}" method="POST"
                                onsubmit="return confirm('Remover este comprovante da transação?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Remover">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-muted mb-3">Nenhum comprovante vinculado a esta transação.</p>
        @endif

        <div class="row g-3">
            <div class="col-md-6">
                <form action="{{ route('transactions.receipts.store', $transaction) }}" method="POST" enctype="multipart/form-data" id="receipt-upload-form">
                    @csrf
                    <label class="form-label fw-semibold">Enviar novo(s)</label>
                    <input type="file" name="files[]" id="receipt-upload-files" class="form-control @error('files') is-invalid @enderror @error('files.*') is-invalid @enderror"
                        accept=".pdf,.jpg,.jpeg,.png,.webp" multiple>
                    <small class="text-muted d-block mt-1">PDF ou imagem — até 10 MB cada</small>
                    @error('files')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @error('files.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-upload"></i> Só anexar
                        </button>
                        <button type="button" class="btn btn-info btn-sm" id="receipt-upload-and-read">
                            <i class="bi bi-magic"></i> Ler e preencher formulário
                        </button>
                    </div>
                </form>
            </div>
            @if($unlinkedDocuments->isNotEmpty())
            <div class="col-md-6">
                <form action="{{ route('transactions.receipts.link', $transaction) }}" method="POST">
                    @csrf
                    <label class="form-label fw-semibold">Vincular documento existente</label>
                    <select name="document_id" class="form-select @error('document_id') is-invalid @enderror" required>
                        <option value="">— Selecione —</option>
                        @foreach($unlinkedDocuments as $doc)
                            <option value="{{ $doc->id }}">{{ $doc->original_name }} ({{ $doc->created_at->format('d/m/Y') }})</option>
                        @endforeach
                    </select>
                    @error('document_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="text-muted d-block mt-1">
                        Arquivos em <a href="{{ route('documents.index') }}">Documentos & OCR</a> sem transação.
                    </small>
                    <button type="submit" class="btn btn-outline-secondary btn-sm mt-2">
                        <i class="bi bi-link-45deg"></i> Vincular
                    </button>
                </form>
            </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_token"]')?.value;
    const extractUrl = @json(route('transactions.receipts.extract', $transaction));

    document.querySelectorAll('.btn-apply-receipt').forEach(btn => {
        btn.addEventListener('click', async function () {
            btn.disabled = true;
            try {
                const res = await fetch(btn.dataset.url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });
                const json = await res.json();
                if (json.ok && typeof window.applyReceiptExtract === 'function') {
                    window.applyReceiptExtract(json.data);
                    alert('Dados aplicados no formulário acima. Revise e clique em Salvar alterações.');
                } else {
                    alert(json.message || 'Não foi possível ler o comprovante.');
                }
            } finally {
                btn.disabled = false;
            }
        });
    });

    document.getElementById('receipt-upload-and-read')?.addEventListener('click', async function () {
        const input = document.getElementById('receipt-upload-files');
        if (!input?.files?.length) {
            alert('Selecione ao menos um arquivo.');
            return;
        }
        const file = input.files[0];
        const body = new FormData();
        body.append('file', file);

        this.disabled = true;
        try {
            const res = await fetch(extractUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body,
            });
            const json = await res.json();
            if (json.ok && typeof window.applyReceiptExtract === 'function') {
                window.applyReceiptExtract(json.data);
                alert('Comprovante lido e anexado. Dados no formulário — revise e salve.');
                location.reload();
            } else {
                alert(json.message || 'Falha na leitura.');
            }
        } finally {
            this.disabled = false;
        }
    });
})();
</script>
@endpush
