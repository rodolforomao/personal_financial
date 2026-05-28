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
                <a class="nav-link" data-bs-toggle="dropdown" href="#">
                    <i class="bi bi-bell-fill"></i>
                    @php $alertCount = \Modules\Alerts\Infrastructure\Models\Alert::where('workspace_id', session('workspace_id'))->where('is_read', false)->count(); @endphp
                    @if($alertCount > 0)
                        <span class="navbar-badge badge text-bg-warning">{{ $alertCount }}</span>
                    @endif
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                    <span class="dropdown-item dropdown-header">{{ $alertCount }} alertas</span>
                    <div class="dropdown-divider"></div>
                    <a href="{{ route('alerts.index') }}" class="dropdown-item dropdown-footer">Ver todos</a>
                </div>
            </li>
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
