<ul class="nav nav-pills mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ ($view ?? 'resumo') === 'resumo' ? 'active' : '' }}"
           href="{{ $summaryUrl }}">
            <i class="bi bi-pie-chart"></i> Resumo
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ ($view ?? 'resumo') === 'detalhado' ? 'active' : '' }}"
           href="{{ $detailUrl }}">
            <i class="bi bi-table"></i> Detalhado
        </a>
    </li>
</ul>
