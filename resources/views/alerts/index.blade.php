@extends('layouts.adminlte')

@section('title', 'Alertas')
@section('page_title', 'Alertas inteligentes')
@section('breadcrumb')<li class="breadcrumb-item active">Alertas</li>@endsection

@push('styles')
<style>
    .bulk-bar {
        position: sticky;
        bottom: 1rem;
        z-index: 100;
        display: none;
    }
    .bulk-bar.active { display: block; }

    .alert-row .row-checkbox { opacity: .35; transition: opacity .1s; }
    .alert-row:hover .row-checkbox,
    .alert-row .row-checkbox:checked { opacity: 1; }


</style>
@endpush

@section('content')

<div class="alert alert-info d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <span>Configure Telegram e WhatsApp para receber alertas fora do painel.</span>
    <div class="d-flex gap-2">
        <a href="{{ route('observability.index') }}" class="btn btn-sm btn-outline-dark">Diagnóstico (logs)</a>
        <a href="{{ route('integrations.settings') }}" class="btn btn-sm btn-primary">Notificações</a>
    </div>
</div>

{{-- Filtros --}}
@php $alertFiltersActive = $severityFilter || $statusFilter; @endphp
<div class="card mb-3">
    <div class="card-header py-2 d-flex justify-content-between align-items-center filter-card-header @if(!$alertFiltersActive) collapsed @endif"
         data-bs-toggle="collapse" data-bs-target="#alert-filter-collapse"
         aria-expanded="{{ $alertFiltersActive ? 'true' : 'false' }}" aria-controls="alert-filter-collapse">
        <h3 class="card-title mb-0 small">
            <i class="bi bi-funnel"></i> Filtros
            <i class="bi bi-chevron-down ms-1 filter-chevron" style="font-size:.75rem"></i>
        </h3>
        @if($alertFiltersActive)
            <span class="badge text-bg-primary">Filtros ativos</span>
        @endif
    </div>
    <div class="collapse @if($alertFiltersActive) show @endif" id="alert-filter-collapse">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="btn-group btn-group-sm" role="group" aria-label="Filtrar por severidade">
                    @php
                        $sevOptions = [
                            ''         => ['label' => 'Todos',       'color' => 'outline-secondary'],
                            'critical' => ['label' => 'Crítico',     'color' => 'danger'],
                            'warning'  => ['label' => 'Atenção',     'color' => 'warning'],
                            'info'     => ['label' => 'Informação',  'color' => 'info'],
                        ];
                    @endphp
                    @foreach($sevOptions as $val => $opt)
                        <a href="{{ route('alerts.index', array_filter(['severity' => $val, 'status' => $statusFilter])) }}"
                           class="btn btn-{{ $opt['color'] }}{{ $severityFilter === $val ? '' : ' opacity-50' }}">
                            {{ $opt['label'] }}
                        </a>
                    @endforeach
                </div>
                <div class="btn-group btn-group-sm" role="group" aria-label="Filtrar por status">
                    @php
                        $statusOptions = [
                            ''       => 'Todos',
                            'unread' => 'Não lidos',
                            'read'   => 'Lidos',
                        ];
                    @endphp
                    @foreach($statusOptions as $val => $label)
                        <a href="{{ route('alerts.index', array_filter(['severity' => $severityFilter, 'status' => $val])) }}"
                           class="btn btn-outline-secondary{{ $statusFilter === $val ? '' : ' opacity-50' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
                @if($alertFiltersActive)
                    <a href="{{ route('alerts.index', ['clear' => 1]) }}" class="btn btn-sm btn-link text-muted p-0 ms-1">
                        <i class="bi bi-x-circle"></i> Limpar filtros
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('alerts.bulk-read') }}" id="bulk-form">
    @csrf

    <div class="card">
        {{-- Cabeçalho com select-all --}}
        <div class="card-header d-flex align-items-center gap-3 py-2">
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" id="select-all" title="Selecionar todos">
                <label class="form-check-label text-muted small" for="select-all">Selecionar todos</label>
            </div>
            <span class="text-muted small ms-auto" id="selection-count" style="display:none"></span>
        </div>

        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                @forelse($alerts as $alert)
                    @php
                        $sevColor = match($alert->severity->value) {
                            'critical' => 'danger',
                            'warning'  => 'warning',
                            default    => 'info',
                        };
                        $sevLabel = match($alert->severity->value) {
                            'critical' => 'Crítico',
                            'warning'  => 'Atenção',
                            default    => 'Informação',
                        };
                    @endphp
                    <li id="alert-{{ $alert->id }}"
                        class="list-group-item alert-row d-flex align-items-start gap-3 {{ $alert->is_read ? '' : 'list-group-item-warning' }}"
                        data-alert-id="{{ $alert->id }}"
                        data-sev-color="{{ $sevColor }}"
                        data-sev-label="{{ $sevLabel }}"
                        data-title="{{ $alert->title }}"
                        data-message="{{ $alert->message }}"
                        data-date="{{ $alert->triggered_at->format('d/m/Y H:i') }}"
                        data-is-read="{{ $alert->is_read ? '1' : '0' }}"
                        data-read-url="{{ route('alerts.read', $alert) }}">

                        {{-- Checkbox --}}
                        <div class="form-check mb-0 flex-shrink-0 pt-1">
                            <input class="form-check-input row-checkbox"
                                   type="checkbox"
                                   name="alert_ids[]"
                                   value="{{ $alert->id }}"
                                   {{ $alert->is_read ? 'data-read=1' : '' }}>
                        </div>

                        {{-- Conteúdo (clicável → abre modal) --}}
                        <button type="button"
                                class="flex-grow-1 min-w-0 text-start border-0 bg-transparent p-0 btn-open-alert"
                                data-alert-id="{{ $alert->id }}">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge text-bg-{{ $sevColor }}">{{ $sevLabel }}</span>
                                <strong>{{ $alert->title }}</strong>
                                @if($alert->is_read)
                                    <span class="badge text-bg-light text-muted border">Lido</span>
                                @endif
                            </div>
                            <p class="mb-0 text-muted small mt-1">{{ $alert->message }}</p>
                            <small class="text-muted">{{ $alert->triggered_at->format('d/m/Y H:i') }}</small>
                        </button>

                        {{-- Botão marcar lido rápido --}}
                        @unless($alert->is_read)
                            <button type="button"
                                    class="btn btn-sm btn-outline-success flex-shrink-0 btn-mark-single"
                                    data-url="{{ route('alerts.read', $alert) }}"
                                    title="Marcar como lido">
                                <i class="bi bi-check-lg"></i>
                            </button>
                        @endunless
                    </li>
                @empty
                    <li class="list-group-item text-center text-muted py-4">Nenhum alerta encontrado.</li>
                @endforelse
            </ul>
        </div>

        <div class="card-footer">{{ $alerts->links() }}</div>
    </div>

    {{-- Barra de ação em massa (sticky na parte de baixo) --}}
    <div class="bulk-bar" id="bulk-bar">
        <div class="card shadow border-0 bg-dark text-white mx-auto" style="max-width:480px">
            <div class="card-body d-flex align-items-center justify-content-between gap-3 py-2 px-3">
                <span id="bulk-label" class="small">0 selecionados</span>
                <div class="d-flex gap-2">
                    <button type="button" id="deselect-all" class="btn btn-sm btn-outline-light">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="bi bi-check2-all me-1"></i>Marcar como lido
                    </button>
                </div>
            </div>
        </div>
    </div>

</form>

{{-- Modal de detalhe do alerta --}}
<div class="modal fade" id="alertDetailModal" tabindex="-1" aria-labelledby="alertDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" id="alertDetailHeader">
                <h5 class="modal-title d-flex align-items-center gap-2" id="alertDetailModalLabel">
                    <span class="badge" id="alertDetailBadge"></span>
                    <span id="alertDetailTitle"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p id="alertDetailMessage" class="mb-3"></p>
                <small class="text-muted"><i class="bi bi-clock"></i> <span id="alertDetailDate"></span></small>
            </div>
            <div class="modal-footer">
                <button type="button"
                        class="btn btn-success"
                        id="alertDetailMarkRead"
                        style="display:none">
                    <i class="bi bi-check-lg me-1"></i> Marcar como lido
                </button>
                <a href="{{ route('alerts.index') }}" id="alertDetailSeeAll" class="btn btn-outline-secondary">
                    Ver todos os alertas
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const form       = document.getElementById('bulk-form');
    const selectAll  = document.getElementById('select-all');
    const bulkBar    = document.getElementById('bulk-bar');
    const bulkLabel  = document.getElementById('bulk-label');
    const countEl    = document.getElementById('selection-count');
    const deselectBtn = document.getElementById('deselect-all');

    function checkboxes() {
        return [...form.querySelectorAll('.row-checkbox')];
    }

    function update() {
        const all      = checkboxes();
        const checked  = all.filter((c) => c.checked);
        const unread   = checked.filter((c) => !c.dataset.read);
        const total    = all.length;
        const n        = checked.length;

        // Select-all state
        selectAll.indeterminate = n > 0 && n < total;
        selectAll.checked = n === total && total > 0;

        // Bulk bar
        if (n > 0) {
            bulkBar.classList.add('active');
            const unreadCount = unread.length;
            bulkLabel.textContent = n === 1
                ? '1 selecionado'
                : `${n} selecionados`;
            if (unreadCount < n) {
                bulkLabel.textContent += ` (${unreadCount} não lido${unreadCount !== 1 ? 's' : ''})`;
            }
        } else {
            bulkBar.classList.remove('active');
        }

        // Inline count next to select-all
        if (n > 0) {
            countEl.textContent = `${n} de ${total} selecionados`;
            countEl.style.display = '';
        } else {
            countEl.style.display = 'none';
        }
    }

    selectAll.addEventListener('change', () => {
        checkboxes().forEach((c) => { c.checked = selectAll.checked; });
        update();
    });

    form.addEventListener('change', (e) => {
        if (e.target.classList.contains('row-checkbox')) update();
    });

    deselectBtn.addEventListener('click', () => {
        checkboxes().forEach((c) => { c.checked = false; });
        selectAll.checked = false;
        selectAll.indeterminate = false;
        update();
    });

    update();

    // ── Modal de detalhe ──────────────────────────────────────────────────
    const alertModal   = new bootstrap.Modal(document.getElementById('alertDetailModal'));
    const modalBadge   = document.getElementById('alertDetailBadge');
    const modalTitle   = document.getElementById('alertDetailTitle');
    const modalMessage = document.getElementById('alertDetailMessage');
    const modalDate    = document.getElementById('alertDetailDate');
    const modalMarkBtn = document.getElementById('alertDetailMarkRead');
    const csrf         = document.querySelector('meta[name="csrf-token"]')?.content;

    function openAlertModal(id) {
        const row = document.getElementById('alert-' + id);
        if (!row) return;

        const color   = row.dataset.sevColor;
        const label   = row.dataset.sevLabel;
        const isRead  = row.dataset.isRead === '1';
        const readUrl = row.dataset.readUrl;

        modalBadge.className   = 'badge text-bg-' + color;
        modalBadge.textContent = label;
        modalTitle.textContent  = row.dataset.title;
        modalMessage.textContent = row.dataset.message;
        modalDate.textContent   = row.dataset.date;

        if (!isRead && readUrl) {
            modalMarkBtn.style.display = '';
            modalMarkBtn.dataset.alertId = id;
            modalMarkBtn.dataset.url     = readUrl;
        } else {
            modalMarkBtn.style.display = 'none';
        }

        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        alertModal.show();
    }

    // Clique no conteúdo da linha → abre modal
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-open-alert');
        if (!btn) return;
        openAlertModal(btn.dataset.alertId);
    });

    // Botão "Marcar como lido" dentro da modal
    modalMarkBtn.addEventListener('click', () => {
        const id  = modalMarkBtn.dataset.alertId;
        const url = modalMarkBtn.dataset.url;
        if (!url || !csrf) return;

        modalMarkBtn.disabled = true;
        modalMarkBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        fetch(url, {
            method: 'PATCH',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        })
        .then((r) => r.ok ? r.json() : Promise.reject())
        .then(() => {
            alertModal.hide();
            const row = document.getElementById('alert-' + id);
            if (row) {
                row.classList.remove('list-group-item-warning');
                row.dataset.isRead = '1';
                const cb = row.querySelector('.row-checkbox');
                if (cb) cb.dataset.read = '1';
                const quickBtn = row.querySelector('.btn-mark-single');
                if (quickBtn) quickBtn.remove();
                const contentBtn = row.querySelector('.btn-open-alert');
                if (contentBtn && !contentBtn.querySelector('.badge-lido')) {
                    const lido = document.createElement('span');
                    lido.className = 'badge text-bg-light text-muted border badge-lido';
                    lido.textContent = 'Lido';
                    contentBtn.querySelector('strong')?.insertAdjacentElement('afterend', lido);
                }
                update();
            }
        })
        .catch(() => {
            modalMarkBtn.disabled = false;
            modalMarkBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Marcar como lido';
        });
    });

    // Chegar via âncora (#alert-xxx) → abre modal
    (function () {
        const hash = window.location.hash;
        if (!hash || !hash.startsWith('#alert-')) return;
        const id = hash.replace('#alert-', '');
        setTimeout(() => openAlertModal(id), 150);
    })();

    // Marcar lido individualmente via fetch (sem form aninhado)
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-mark-single');
        if (!btn) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        fetch(btn.dataset.url, {
            method: 'PATCH',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        })
        .then((r) => r.ok ? r.json() : Promise.reject())
        .then(() => {
            const row = btn.closest('.alert-row');
            if (!row) return;

            // Atualiza visual da linha
            row.classList.remove('list-group-item-warning');
            const cb = row.querySelector('.row-checkbox');
            if (cb) cb.dataset.read = '1';
            btn.remove();

            // Insere badge "Lido"
            const strong = row.querySelector('strong');
            if (strong && !row.querySelector('.badge-lido')) {
                const badge = document.createElement('span');
                badge.className = 'badge text-bg-light text-muted border badge-lido';
                badge.textContent = 'Lido';
                strong.insertAdjacentElement('afterend', badge);
            }

            update();
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i>';
        });
    });
})();
</script>
@endpush
