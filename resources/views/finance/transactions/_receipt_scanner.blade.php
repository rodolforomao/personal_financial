@php
    $scanTarget = $scanTarget ?? 'transaction-form';
    $scanTransactionId = $scanTransactionId ?? null;
@endphp
<div class="card card-outline card-info mb-3" id="receipt-scanner-card">
    <div class="card-header">
        <h3 class="card-title mb-0"><i class="bi bi-file-earmark-image"></i> Ler comprovante (PDF ou imagem)</h3>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Envie o comprovante para extrair valor, data, descrição e contraparte.
            Sugerimos uma <strong>categoria (opcional)</strong> — confirme ou deixe em branco antes de salvar.
        </p>
        <div class="row g-2 align-items-end">
            <div class="col-md-8">
                <input type="file" id="receipt-scan-file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
            </div>
            <div class="col-md-4">
                <button type="button" class="btn btn-info w-100" id="receipt-scan-btn">
                    <i class="bi bi-magic"></i> Ler e preencher
                </button>
            </div>
        </div>
        <div id="receipt-scan-status" class="mt-2 small"></div>
        <div id="receipt-scan-preview" class="alert alert-light border mt-2 d-none"></div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const form = document.getElementById('{{ $scanTarget }}');
    if (!form) return;

    const fileInput = document.getElementById('receipt-scan-file');
    const btn = document.getElementById('receipt-scan-btn');
    const statusEl = document.getElementById('receipt-scan-status');
    const previewEl = document.getElementById('receipt-scan-preview');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content
        || form.querySelector('input[name="_token"]')?.value;

    const previewUrl = @json($scanTransactionId
        ? route('transactions.receipts.extract', $scanTransactionId)
        : route('transactions.receipt-extract.preview'));

    function setStatus(msg, type) {
        statusEl.className = 'mt-2 small text-' + (type || 'muted');
        statusEl.textContent = msg;
    }

    function fillField(name, value) {
        const el = form.querySelector('[name="' + name + '"]');
        if (!el || value === null || value === undefined || value === '') return;
        el.value = value;
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function applyData(data) {
        fillField('type', data.type);
        fillField('amount', data.amount > 0 ? data.amount : '');
        fillField('transaction_date', data.transaction_date);
        fillField('description', data.description);
        fillField('counterparty', data.counterparty);
        if (data.category_id) fillField('category_id', data.category_id);
        if (data.funding_source) fillField('funding_source', data.funding_source);
        if (data.payment_method) fillField('payment_method', data.payment_method);

        if (data.category_suggestion && typeof window.showCategorySuggestion === 'function') {
            window.showCategorySuggestion(data.category_suggestion);
        }

        const lines = [
            '<strong>Dados extraídos</strong>',
            'Tipo: ' + (data.type === 'income' ? 'Receita' : 'Despesa'),
            'Valor: R$ ' + (data.amount || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 }),
            'Data: ' + (data.transaction_date || '—'),
            'Descrição: ' + (data.description || '—'),
        ];
        if (data.counterparty) lines.push('Contraparte: ' + data.counterparty);
        if (data.funding_source) lines.push('Fonte: ' + data.funding_source);
        if (data.payment_method) lines.push('Meio: ' + data.payment_method);
        if (data.bank) lines.push('Banco (OCR): ' + data.bank);
        const rec = data.category_suggestion?.recommended;
        if (rec) {
            lines.push('Categoria sugerida: <strong>' + rec.name + '</strong> (opcional)');
        }
        previewEl.innerHTML = lines.join('<br>') + '<br><span class="text-warning">Confira categoria e demais campos antes de salvar.</span>';
        previewEl.classList.remove('d-none');
    }

    btn?.addEventListener('click', async function () {
        if (!fileInput?.files?.length) {
            setStatus('Selecione um PDF ou imagem.', 'danger');
            return;
        }

        btn.disabled = true;
        setStatus('Lendo comprovante…', 'primary');
        previewEl.classList.add('d-none');

        const body = new FormData();
        body.append('file', fileInput.files[0]);
        if (@json($scanTransactionId)) {
            body.append('_method', 'POST');
        }

        try {
            const res = await fetch(previewUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body,
            });
            const json = await res.json();
            if (!res.ok || !json.ok) {
                setStatus(json.message || 'Falha na leitura.', 'danger');
                return;
            }
            applyData(json.data);
            setStatus(json.warning || 'Formulário preenchido. Revise os campos.', 'success');
        } catch (e) {
            setStatus('Erro de rede ao ler o comprovante.', 'danger');
        } finally {
            btn.disabled = false;
        }
    });

    window.applyReceiptExtract = applyData;
})();
</script>
@endpush
