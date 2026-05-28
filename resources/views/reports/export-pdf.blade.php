<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório financeiro</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; }
        h1 { font-size: 14px; margin: 0 0 4px; }
        .meta { margin-bottom: 12px; color: #555; line-height: 1.4; }
        .totals { margin-bottom: 10px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 5px; text-align: left; vertical-align: top; }
        th { background: #343a40; color: #fff; font-size: 8px; }
        td.amount { text-align: right; white-space: nowrap; }
        tr:nth-child(even) { background: #f8f9fa; }
        .type-income { color: #198754; }
        .type-expense { color: #dc3545; }
    </style>
</head>
<body>
    <h1>Relatório financeiro — {{ $workspace_name }}</h1>
    <div class="meta">
        <div>Período: {{ $period_label }}</div>
        <div>Gerado em: {{ $generated_at }}</div>
    </div>
    <div class="totals">
        Receitas: R$ {{ number_format($totals['income'], 2, ',', '.') }}
        · Despesas: R$ {{ number_format($totals['expense'], 2, ',', '.') }}
        · Líquido: R$ {{ number_format($totals['net'], 2, ',', '.') }}
        · {{ $totals['transaction_count'] }} lançamento(s)
    </div>

    @if(count($rows) === 0)
        <p>Nenhum lançamento confirmado ou conciliado corresponde aos filtros.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Nº</th>
                    <th>Tipo</th>
                    <th>Data</th>
                    <th>Descrição</th>
                    <th>Categoria</th>
                    <th>Classificação</th>
                    <th>Pagamento realizado por</th>
                    <th>Valor (R$)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $row['number'] }}</td>
                        <td class="{{ str_starts_with($row['number'], 'R-') ? 'type-income' : (str_starts_with($row['number'], 'D-') ? 'type-expense' : '') }}">
                            {{ $row['type_label'] }}
                        </td>
                        <td>{{ $row['date'] }}</td>
                        <td>{{ $row['description'] }}</td>
                        <td>{{ $row['category'] }}</td>
                        <td>{{ $row['classification'] }}</td>
                        <td>{{ $row['paid_by'] }}</td>
                        <td class="amount">{{ $row['amount_formatted'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
