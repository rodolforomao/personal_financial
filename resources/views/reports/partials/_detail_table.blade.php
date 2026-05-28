@if(count($detailRows) === 0)
    <div class="alert alert-info mb-0">
        Nenhum lançamento confirmado ou conciliado corresponde aos filtros.
        <a href="{{ route('reports.index', ['view' => 'detalhado']) }}">Limpar filtros</a>
    </div>
@else
<div class="card card-outline card-secondary">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h3 class="card-title mb-0">Lançamentos do relatório</h3>
        <span class="text-muted small">{{ count($detailRows) }} registro(s) · ordenados por data</span>
    </div>
    <div class="card-body table-responsive p-0">
        <table id="report-transactions-table" class="table table-striped table-hover mb-0 w-100">
            <thead>
                <tr>
                    <th>Nº</th>
                    <th>Tipo</th>
                    <th>Data</th>
                    <th>Descrição</th>
                    <th>Categoria</th>
                    <th>Classificação</th>
                    <th>Pagamento realizado por</th>
                    <th class="text-end">Valor (R$)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($detailRows as $row)
                    <tr>
                        <td>{{ $row['number'] }}</td>
                        <td data-order="{{ $row['type_label'] }}">
                            <span class="badge text-bg-{{ str_starts_with($row['number'], 'R-') ? 'success' : (str_starts_with($row['number'], 'D-') ? 'danger' : 'secondary') }}">
                                {{ $row['type_label'] }}
                            </span>
                        </td>
                        <td data-order="{{ $row['date_sort'] }}">{{ $row['date'] }}</td>
                        <td>{{ $row['description'] }}</td>
                        <td>{{ $row['category'] }}</td>
                        <td>{{ $row['classification'] }}</td>
                        <td>{{ $row['paid_by'] }}</td>
                        <td class="text-end" data-order="{{ $row['amount'] }}">
                            {{ $row['amount_formatted'] }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
