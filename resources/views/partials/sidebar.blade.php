<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
    <div class="sidebar-brand">
        <a href="{{ route('dashboard') }}" class="brand-link">
            <span class="brand-text fw-light ms-2">{!! config('adminlte.logo') !!}</span>
        </a>
    </div>
    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation" data-accordion="false">
                @foreach(config('adminlte.menu') as $item)
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
