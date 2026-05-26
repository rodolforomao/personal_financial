<nav class="app-header navbar navbar-expand bg-body">
    <div class="container-fluid">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                    <i class="bi bi-list"></i>
                </a>
            </li>
            <li class="nav-item d-none d-md-block">
                <span class="nav-link fiq-workspace-pill">
                    <i class="bi bi-building-check"></i>
                    {{ $currentWorkspace->name ?? 'Workspace' }}
                </span>
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
                    <li class="user-footer">
                        <form action="{{ route('logout') }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm float-end">Sair</button>
                        </form>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>
