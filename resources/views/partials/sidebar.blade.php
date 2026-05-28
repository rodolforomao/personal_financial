@php
    $menuItems = $sidebarMenu ?? config('adminlte.menu', []);
@endphp
<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
    <div class="sidebar-brand fiq-sidebar-brand">
        <a href="{{ route('dashboard') }}" class="brand-link fiq-brand-link">
            @php
                $sidebarLogo = file_exists(public_path('financialiq/logo_on_dark_48x48.png'))
                    ? 'financialiq/logo_on_dark_48x48.png'
                    : (file_exists(public_path('financialiq/logo_on_dark_transparent.png'))
                        ? 'financialiq/logo_on_dark_transparent.png'
                        : (file_exists(public_path('financialiq/logo_transparent.png'))
                            ? 'financialiq/logo_transparent.png'
                            : null));
            @endphp
            @if($sidebarLogo)
                <img src="{{ asset($sidebarLogo) }}" alt="FinancialIQ" class="brand-image fiq-brand-logo" width="32" height="32">
            @endif
            <span class="fiq-brand-wordmark">
                Financial<span>IQ</span>
                <small class="fiq-brand-subtitle">CFO Intelligence</small>
            </span>
        </a>
    </div>
    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation" data-accordion="false">
                @foreach($menuItems as $item)
                    @if(!empty($item['role']) && !auth()->user()?->hasRole($item['role']))
                        @continue
                    @endif
                    @if(isset($item['header']))
                        <li class="nav-header">{{ $item['header'] }}</li>
                    @else
                        @php
                            $active = request()->routeIs($item['route'].'*') || request()->routeIs($item['route']);
                        @endphp
                        <li class="nav-item">
                            <a href="{{ route($item['route']) }}" class="nav-link {{ $active ? 'active' : '' }}">
                                <i class="nav-icon {{ $item['icon'] }}"></i>
                                <p>{{ $item['text'] }}</p>
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>
    </div>
</aside>
