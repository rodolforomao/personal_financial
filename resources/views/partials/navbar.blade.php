<nav class="app-header navbar navbar-expand bg-body">
    <div class="container-fluid">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                    <i class="bi bi-list"></i>
                </a>
            </li>
            <li class="nav-item d-none d-md-block">
                @php $workspaceList = $availableWorkspaces ?? collect(); @endphp
                <div class="d-flex align-items-center ms-2 gap-1">
                    <i class="bi bi-building-check"></i>
                    @if($workspaceList->count() > 1)
                        <form action="{{ route('workspace.switch') }}" method="POST" class="d-flex align-items-center">
                            @csrf
                            <input type="hidden" name="redirect_to" value="{{ url()->current() }}">
                            <select name="workspace_id" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Workspace">
                                @foreach($workspaceList as $ws)
                                    <option value="{{ $ws->id }}" @selected(($currentWorkspace->id ?? null) === $ws->id)>
                                        {{ $ws->name }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    @else
                        <span class="nav-link py-0 px-1 fiq-workspace-pill">
                            {{ $currentWorkspace->name ?? 'Workspace' }}
                        </span>
                    @endif
                    <a href="{{ route('workspace.index') }}" class="nav-link py-0 px-1" title="Gerenciar workspaces">
                        <i class="bi bi-gear-wide-connected"></i>
                    </a>
                </div>
            </li>
        </ul>
        <ul class="navbar-nav ms-auto">
            <li class="nav-item dropdown">
                @php
                    $navAlertCount   = \Modules\Alerts\Infrastructure\Models\Alert::where('workspace_id', session('workspace_id'))->where('is_read', false)->count();
                    $navAlertRecent  = \Modules\Alerts\Infrastructure\Models\Alert::where('workspace_id', session('workspace_id'))->where('is_read', false)->orderByDesc('triggered_at')->limit(5)->get();
                    $navSevColor     = fn ($v) => match ($v) { 'critical' => 'danger', 'warning' => 'warning', default => 'info' };
                    $navSevLabel     = fn ($v) => match ($v) { 'critical' => 'Crítico', 'warning' => 'Atenção', default => 'Info' };
                @endphp
                <a class="nav-link" data-bs-toggle="dropdown" href="#" id="nav-bell-toggle">
                    <i class="bi bi-bell-fill"></i>
                    <span class="navbar-badge badge text-bg-warning" id="nav-alert-badge" @if($navAlertCount === 0) style="display:none" @endif>
                        {{ $navAlertCount }}
                    </span>
                </a>
                <div class="dropdown-menu dropdown-menu-end p-0" style="min-width:300px; max-width:340px">
                    <div class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                        <span class="small fw-semibold" id="nav-alert-header">
                            {{ $navAlertCount > 0 ? $navAlertCount.' não lido'.($navAlertCount > 1 ? 's' : '') : 'Sem alertas' }}
                        </span>
                        <a href="{{ route('alerts.index') }}" class="small text-muted">Ver todos</a>
                    </div>
                    <ul class="list-unstyled mb-0" id="nav-alert-list">
                        @forelse($navAlertRecent as $navAlert)
                            <li class="nav-alert-item d-flex align-items-center gap-2 px-3 border-bottom"
                                style="min-height:38px; padding-top:.4rem; padding-bottom:.4rem"
                                data-alert-id="{{ $navAlert->id }}">
                                {{-- Ponto colorido de severidade --}}
                                <span class="flex-shrink-0 rounded-circle bg-{{ $navSevColor($navAlert->severity->value) }}"
                                      style="width:8px; height:8px; display:inline-block"
                                      title="{{ $navSevLabel($navAlert->severity->value) }}"></span>
                                {{-- Título + hora (link para o alerta) --}}
                                <a href="{{ route('alerts.index') }}#alert-{{ $navAlert->id }}"
                                   class="flex-grow-1 min-w-0 text-decoration-none text-body"
                                   style="font-size:.8rem; line-height:1.2">
                                    <span class="d-block text-truncate fw-semibold">{{ $navAlert->title }}</span>
                                    <span class="text-muted" style="font-size:.7rem">{{ $navAlert->triggered_at->format('d/m H:i') }}</span>
                                </a>
                                {{-- Botão marcar lido --}}
                                <button type="button"
                                        class="btn btn-link btn-sm flex-shrink-0 text-success p-0 nav-btn-mark-read"
                                        data-url="{{ route('alerts.read', $navAlert) }}"
                                        title="Marcar como lido"
                                        style="font-size:1rem; line-height:1">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                            </li>
                        @empty
                            <li class="px-3 py-3 text-muted small text-center">Sem alertas não lidos.</li>
                        @endforelse
                    </ul>
                </div>
            </li>
            <script>
            (function () {
                const csrf    = document.querySelector('meta[name="csrf-token"]')?.content;
                const badge   = document.getElementById('nav-alert-badge');
                const header  = document.getElementById('nav-alert-header');
                const list    = document.getElementById('nav-alert-list');

                function getCount() {
                    return list ? list.querySelectorAll('.nav-alert-item').length : 0;
                }

                function updateHeader(n) {
                    if (!header) return;
                    header.textContent = n > 0
                        ? `${n} alerta${n > 1 ? 's' : ''} não lido${n > 1 ? 's' : ''}`
                        : 'Sem alertas';
                }

                function updateBadge(n) {
                    if (!badge) return;
                    badge.textContent = n;
                    badge.style.display = n > 0 ? '' : 'none';
                }

                document.addEventListener('click', (e) => {
                    const btn = e.target.closest('.nav-btn-mark-read');
                    if (!btn || !csrf) return;

                    btn.disabled = true;

                    fetch(btn.dataset.url, {
                        method: 'PATCH',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    })
                    .then((r) => r.ok ? r.json() : Promise.reject())
                    .then(() => {
                        btn.closest('.nav-alert-item')?.remove();
                        const n = getCount();
                        updateBadge(n);
                        updateHeader(n);
                        if (n === 0 && list) {
                            list.innerHTML = '<li class="px-3 py-3 text-muted small text-center">Nenhum alerta não lido.</li>';
                        }
                    })
                    .catch(() => { btn.disabled = false; });
                });
            })();
            </script>
            <li class="nav-item dropdown user-menu">
                <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle fs-4"></i>
                    <span class="d-none d-md-inline">{{ auth()->user()->name }}</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                    <li class="user-header text-bg-primary">
                        <p class="mb-0">{{ auth()->user()->name }}<small>{{ auth()->user()->email }}</small></p>
                    </li>
                    <li class="user-footer d-flex justify-content-between gap-2 px-3 py-2">
                        <a href="{{ route('account.security') }}" class="btn btn-outline-secondary btn-sm">Conta</a>
                        <form action="{{ route('logout') }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm">Sair</button>
                        </form>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>
