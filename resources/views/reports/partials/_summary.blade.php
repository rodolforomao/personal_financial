@if($totals['transaction_count'] === 0)
    <div class="alert alert-info">
        Nenhum lançamento confirmado ou conciliado corresponde aos filtros.
        <a href="{{ route('reports.index') }}">Limpar filtros</a> ou
        <a href="{{ route('transactions.index') }}">ir às transações</a>.
    </div>
@else
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card card-outline card-secondary h-100">
            <div class="card-header">
                <h3 class="card-title mb-0">Por operação</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Operação</th>
                                <th class="text-end">Receita</th>
                                <th class="text-end">Despesa</th>
                                <th class="text-end">Líquido</th>
                                <th class="text-end">Qtd.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report['by_operation'] as $row)
                                <tr>
                                    <td>
                                        @if($row['operation_id'])
                                            <a href="{{ route('reports.index', array_merge(request()->query(), ['operation_id' => $row['operation_id'], 'operation_unit_id' => null, 'view' => 'resumo'])) }}">
                                                {{ $row['label'] }}
                                            </a>
                                        @else
                                            {{ $row['label'] }}
                                        @endif
                                    </td>
                                    <td class="text-end text-success">{{ $money($row['income']) }}</td>
                                    <td class="text-end text-danger">{{ $money($row['expense']) }}</td>
                                    <td class="text-end"><strong>{{ $money($row['net']) }}</strong></td>
                                    <td class="text-end text-muted">{{ $row['count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-outline card-secondary h-100">
            <div class="card-header">
                <h3 class="card-title mb-0">Por categoria</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Categoria</th>
                                <th class="text-end">Receita</th>
                                <th class="text-end">Despesa</th>
                                <th class="text-end">Líquido</th>
                                <th class="text-end">Qtd.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report['by_category'] as $row)
                                <tr>
                                    <td>
                                        @if($row['category_id'])
                                            <a href="{{ route('reports.index', array_merge(request()->query(), ['category_id' => $row['category_id'], 'view' => 'resumo'])) }}">
                                                {{ $row['label'] }}
                                            </a>
                                        @else
                                            {{ $row['label'] }}
                                        @endif
                                    </td>
                                    <td class="text-end text-success">{{ $money($row['income']) }}</td>
                                    <td class="text-end text-danger">{{ $money($row['expense']) }}</td>
                                    <td class="text-end"><strong>{{ $money($row['net']) }}</strong></td>
                                    <td class="text-end text-muted">{{ $row['count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card card-outline card-secondary">
            <div class="card-header">
                <h3 class="card-title mb-0">Por empresa</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Empresa</th>
                                <th class="text-end">Receita</th>
                                <th class="text-end">Despesa</th>
                                <th class="text-end">Líquido</th>
                                <th class="text-end">Qtd.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report['by_company'] as $row)
                                <tr>
                                    <td>
                                        @if($row['company_id'])
                                            <a href="{{ route('reports.index', array_merge(request()->query(), ['company_id' => $row['company_id'], 'view' => 'resumo'])) }}">
                                                {{ $row['label'] }}
                                            </a>
                                        @else
                                            {{ $row['label'] }}
                                        @endif
                                    </td>
                                    <td class="text-end text-success">{{ $money($row['income']) }}</td>
                                    <td class="text-end text-danger">{{ $money($row['expense']) }}</td>
                                    <td class="text-end"><strong>{{ $money($row['net']) }}</strong></td>
                                    <td class="text-end text-muted">{{ $row['count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
