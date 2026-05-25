{{-- Categoria opcional + sugestão após OCR ou ao editar descrição --}}
<div class="col-12 mb-2">
    <div id="category-suggestion-panel" class="alert alert-secondary d-none mb-0 py-2">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="small" id="category-suggestion-text"></div>
            <div class="d-flex gap-1 flex-shrink-0">
                <button type="button" class="btn btn-sm btn-success d-none" id="category-suggestion-accept">
                    <i class="bi bi-check-lg"></i> Usar sugestão
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="category-suggestion-dismiss">
                    Sem categoria
                </button>
            </div>
        </div>
    </div>
</div>
<div class="col-md-6 mb-3">
    <label class="form-label">Categoria <span class="text-muted fw-normal">(opcional)</span></label>
    <select name="category_id" id="tx-category-id" class="form-select">
        <option value="">— Sem categoria / decidir depois —</option>
        @foreach($categories->groupBy('type') as $type => $group)
            <optgroup label="{{ $type === 'income' ? 'Receitas' : 'Despesas' }}" data-category-type="{{ $type }}">
                @foreach($group as $cat)
                    <option value="{{ $cat->id }}" data-type="{{ $cat->type }}"
                        @selected(($selectedCategoryId ?? null) == $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </optgroup>
        @endforeach
    </select>
    <small class="text-muted">Após ler um comprovante, sugerimos uma categoria — você confirma ou ignora.</small>
</div>

@push('scripts')
<script>
(function () {
    const panel = document.getElementById('category-suggestion-panel');
    const textEl = document.getElementById('category-suggestion-text');
    const acceptBtn = document.getElementById('category-suggestion-accept');
    const dismissBtn = document.getElementById('category-suggestion-dismiss');
    const categorySelect = document.getElementById('tx-category-id');
    const typeSelect = document.getElementById('tx-type') || document.querySelector('[name="type"]');
    const form = document.getElementById('transaction-form') || document.getElementById('tx-edit-form');
    const suggestUrl = @json(route('transactions.suggest-category'));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!panel || !categorySelect) return;

    let debounceTimer = null;
    let lastRecommendedId = null;

    function filterCategoriesByType() {
        const type = typeSelect?.value || 'expense';
        const allowed = type === 'income' ? 'income' : (type === 'transfer' ? null : 'expense');
        categorySelect.querySelectorAll('option[data-type]').forEach(opt => {
            if (!allowed) {
                opt.hidden = false;
                return;
            }
            opt.hidden = opt.dataset.type !== allowed;
        });
        categorySelect.querySelectorAll('optgroup[data-category-type]').forEach(og => {
            if (!allowed) {
                og.hidden = false;
                return;
            }
            og.hidden = og.dataset.categoryType !== allowed;
        });
    }

    function plainMessage(html) {
        return (html || '').replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    }

    window.showCategorySuggestion = function (payload) {
        if (!payload) {
            panel.classList.add('d-none');
            return;
        }

        const rec = payload.recommended;
        textEl.innerHTML = plainMessage(payload.message || '');
        panel.classList.remove('d-none');

        if (rec?.category_id) {
            lastRecommendedId = rec.category_id;
            acceptBtn.classList.remove('d-none');
            acceptBtn.textContent = ' Usar ' + (rec.name || 'sugestão');
            acceptBtn.querySelector('i')?.nextSibling;
            acceptBtn.innerHTML = '<i class="bi bi-check-lg"></i> Usar ' + (rec.name || 'sugestão');
        } else {
            lastRecommendedId = null;
            acceptBtn.classList.add('d-none');
        }
    };

    acceptBtn?.addEventListener('click', function () {
        if (lastRecommendedId) {
            categorySelect.value = String(lastRecommendedId);
            categorySelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });

    dismissBtn?.addEventListener('click', function () {
        categorySelect.value = '';
        panel.classList.add('d-none');
    });

    function refreshSuggestions() {
        const description = form?.querySelector('[name="description"]')?.value?.trim() || '';
        const counterparty = form?.querySelector('[name="counterparty"]')?.value?.trim() || '';
        const type = typeSelect?.value || 'expense';

        if (description === '' && counterparty === '') {
            return;
        }

        fetch(suggestUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ description, counterparty, type }),
        })
            .then(r => r.json())
            .then(json => {
                if (json.ok && json.category_suggestion) {
                    window.showCategorySuggestion(json.category_suggestion);
                }
            })
            .catch(() => {});
    }

    const scheduleRefresh = () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(refreshSuggestions, 600);
    };

    form?.querySelector('[name="description"]')?.addEventListener('input', scheduleRefresh);
    form?.querySelector('[name="counterparty"]')?.addEventListener('input', scheduleRefresh);
    typeSelect?.addEventListener('change', function () {
        filterCategoriesByType();
        scheduleRefresh();
    });

    filterCategoriesByType();

    const origApply = window.applyReceiptExtract;
    window.applyReceiptExtract = function (data) {
        if (typeof origApply === 'function') origApply(data);
        if (data?.category_suggestion) {
            window.showCategorySuggestion(data.category_suggestion);
        } else if (data?.category_id) {
            window.showCategorySuggestion({
                recommended: { category_id: data.category_id, name: data.suggested_category_slug || '' },
                message: 'Categoria sugerida pela leitura do comprovante. Opcional.',
            });
        }
        filterCategoriesByType();
    };
})();
</script>
@endpush
