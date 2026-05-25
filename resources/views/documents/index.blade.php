@extends('layouts.adminlte')

@section('title', 'Documentos')
@section('page_title', 'Documentos & OCR')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('transactions.index') }}">Transações</a></li>
    <li class="breadcrumb-item active">OCR</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <p class="text-muted mb-0">Envie comprovantes, leia com OCR e vincule ou crie transações.</p>
    <a href="{{ route('transactions.index') }}" class="btn btn-primary">
        <i class="bi bi-list-ul"></i> Ver transações
    </a>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title">Enviar e ler comprovante</h3></div>
            <div class="card-body">
                <input type="file" name="file" id="document-file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
                <small class="text-muted d-block mt-2">
                    PDF (com IA no .env) ou imagem — extrai dados para nova transação.
                </small>
                <div id="doc-scan-status" class="small mt-2"></div>
            </div>
            <div class="card-footer d-grid gap-2">
                <button type="button" class="btn btn-info" id="doc-read-and-create">
                    <i class="bi bi-magic"></i> Ler e criar transação
                </button>
                <form action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data" id="document-store-form">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary w-100" id="doc-store-btn">
                        Só enviar (fila OCR)
                    </button>
                </form>
            </div>
        </div>
        <div class="card card-outline card-secondary">
            <div class="card-body small">
                <strong>Na lista ao lado</strong>
                <ul class="mb-0 ps-3">
                    <li><em>Ver</em> — abre o arquivo</li>
                    <li><em>Ler</em> — OCR e preenche transação (nova ou vinculada)</li>
                    <li><em>Excluir</em> — remove o anexo (vinculado à transação exige senha)</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-body table-responsive p-0">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Arquivo</th>
                            <th>Transação</th>
                            <th>Status</th>
                            <th>OCR</th>
                            <th class="text-end">Ações</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($documents as $doc)
                            <tr data-document-id="{{ $doc->id }}">
                                <td>
                                    <a href="{{ route('documents.show', $doc) }}" target="_blank" class="text-decoration-none">
                                        <i class="bi bi-file-earmark"></i> {{ $doc->original_name }}
                                    </a>
                                    <div class="small text-muted">{{ number_format($doc->size / 1024, 1) }} KB</div>
                                </td>
                                <td>
                                    @if($doc->transaction_id)
                                        <a href="{{ route('transactions.edit', $doc->transaction_id) }}">#{{ $doc->transaction_id }}</a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td><span class="badge text-bg-secondary">{{ $doc->status }}</span></td>
                                <td>
                                    @if($doc->ocr_result && isset($doc->ocr_result['amount']))
                                        <small>R$ {{ number_format((float) $doc->ocr_result['amount'], 2, ',', '.') }}</small>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm flex-wrap justify-content-end" role="group">
                                        <a href="{{ route('documents.show', $doc) }}" target="_blank" class="btn btn-outline-secondary" title="Ver arquivo">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-info btn-doc-read"
                                            data-url="{{ route('documents.extract', $doc) }}"
                                            data-transaction-id="{{ $doc->transaction_id }}"
                                            data-edit-url="{{ $doc->transaction_id ? route('transactions.edit', $doc->transaction_id) : '' }}"
                                            title="Ler comprovante e preencher transação">
                                            <i class="bi bi-magic"></i>
                                            @if($doc->transaction_id)
                                                Ler
                                            @else
                                                Ler e criar
                                            @endif
                                        </button>
                                        @if($doc->transaction_id && $requirePassword)
                                            <button type="button" class="btn btn-outline-danger btn-doc-delete-linked"
                                                title="Excluir comprovante vinculado"
                                                data-bs-toggle="modal" data-bs-target="#deleteLinkedDocumentModal"
                                                data-document-id="{{ $doc->id }}"
                                                data-action="{{ route('documents.destroy', $doc) }}"
                                                data-name="{{ $doc->original_name }}"
                                                data-transaction-id="{{ $doc->transaction_id }}"
                                                data-edit-url="{{ route('transactions.edit', $doc->transaction_id) }}">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        @else
                                            <form action="{{ route('documents.destroy', $doc) }}" method="POST" class="d-inline"
                                                onsubmit="return confirm('Excluir este documento permanentemente?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger" title="Excluir">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-nowrap">{{ $doc->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Nenhum documento enviado ainda.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($documents->hasPages())
                <div class="card-footer">{{ $documents->links() }}</div>
            @endif
        </div>
    </div>
</div>

@if($requirePassword)
<div class="modal fade" id="deleteLinkedDocumentModal" tabindex="-1" aria-labelledby="deleteLinkedDocumentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" id="delete-linked-document-form" action="">
            @csrf
            @method('DELETE')
            <input type="hidden" name="delete_document_id" id="delete-document-id-field" value="{{ old('delete_document_id') }}">
            <div class="modal-content">
                <div class="modal-header border-danger">
                    <h5 class="modal-title text-danger" id="deleteLinkedDocumentModalLabel">
                        <i class="bi bi-trash"></i> Excluir comprovante
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">
                        <strong id="delete-linked-doc-name"></strong>
                    </p>
                    <p class="text-muted small mb-3">
                        Vinculado à transação <a href="#" id="delete-linked-doc-tx-link" target="_blank">#<span id="delete-linked-doc-tx-id"></span></a>.
                        O arquivo será removido permanentemente; a transação permanece.
                    </p>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Senha da conta</label>
                        <input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror"
                            autocomplete="current-password" required id="delete-linked-doc-password">
                        @error('current_password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger" id="delete-linked-doc-submit">Excluir comprovante</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_token"]')?.value;
    const previewUrl = @json(route('transactions.receipt-extract.preview'));
    const createUrl = @json(route('transactions.create'));

    document.getElementById('document-store-form')?.addEventListener('submit', function (e) {
        const main = document.getElementById('document-file');
        const dt = new DataTransfer();
        if (main?.files?.[0]) dt.items.add(main.files[0]);
        let hidden = document.getElementById('document-file-clone');
        if (!hidden) {
            hidden = document.createElement('input');
            hidden.type = 'file';
            hidden.name = 'file';
            hidden.id = 'document-file-clone';
            hidden.className = 'd-none';
            e.target.appendChild(hidden);
        }
        hidden.files = dt.files;
        if (!hidden.files.length) {
            e.preventDefault();
            alert('Selecione um arquivo.');
        }
    });

    document.getElementById('doc-read-and-create')?.addEventListener('click', async function () {
        const input = document.getElementById('document-file');
        const status = document.getElementById('doc-scan-status');
        if (!input?.files?.length) {
            status.textContent = 'Selecione um arquivo.';
            return;
        }
        status.textContent = 'Lendo…';
        const body = new FormData();
        body.append('file', input.files[0]);
        try {
            const res = await fetch(previewUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body,
            });
            const json = await res.json();
            if (!json.ok) {
                status.textContent = json.message || 'Erro';
                return;
            }
            sessionStorage.setItem('transaction_prefill', JSON.stringify(json.data));
            sessionStorage.removeItem('link_document_id');
            window.location.href = createUrl;
        } catch (e) {
            status.textContent = 'Erro de rede.';
        }
    });

    const deleteModal = document.getElementById('deleteLinkedDocumentModal');
    if (deleteModal) {
        const deleteForm = document.getElementById('delete-linked-document-form');
        const docIdField = document.getElementById('delete-document-id-field');
        const fillDeleteModal = (btn) => {
            deleteForm.action = btn.dataset.action;
            if (docIdField) docIdField.value = btn.dataset.documentId;
            document.getElementById('delete-linked-doc-name').textContent = btn.dataset.name;
            document.getElementById('delete-linked-doc-tx-id').textContent = btn.dataset.transactionId;
            document.getElementById('delete-linked-doc-tx-link').href = btn.dataset.editUrl;
        };
        document.querySelectorAll('.btn-doc-delete-linked').forEach(btn => {
            btn.addEventListener('click', function () {
                fillDeleteModal(btn);
                const pwd = document.getElementById('delete-linked-doc-password');
                if (pwd) pwd.value = '';
            });
        });
        @if($errors->has('current_password'))
        const reopenId = @json(old('delete_document_id'));
        if (reopenId) {
            const trigger = document.querySelector('.btn-doc-delete-linked[data-document-id="' + reopenId + '"]');
            if (trigger) fillDeleteModal(trigger);
            bootstrap.Modal.getOrCreateInstance(deleteModal).show();
        }
        @endif
    }

    document.querySelectorAll('.btn-doc-read').forEach(btn => {
        btn.addEventListener('click', async function () {
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            try {
                const res = await fetch(btn.dataset.url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });
                const json = await res.json();
                if (!json.ok) {
                    alert(json.message || 'Não foi possível ler o comprovante.');
                    return;
                }
                sessionStorage.setItem('transaction_prefill', JSON.stringify(json.data));
                if (json.document_id) {
                    sessionStorage.setItem('link_document_id', String(json.document_id));
                }
                const txId = btn.dataset.transactionId;
                if (txId && btn.dataset.editUrl) {
                    window.location.href = btn.dataset.editUrl;
                } else {
                    window.location.href = createUrl;
                }
            } catch (e) {
                alert('Erro de rede ao ler o documento.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        });
    });
})();
</script>
@endpush
