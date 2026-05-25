@extends('layouts.adminlte')

@section('title', 'Conciliar extrato')
@section('page_title', 'Conciliar: '.$import->original_name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('statements.index') }}">Extrato</a></li>
    <li class="breadcrumb-item active">Conciliação</li>
@endsection

@section('content')
<div class="row mb-3">
    <div class="col-md-8">
        <div class="d-flex flex-wrap gap-2">
            <span class="badge text-bg-secondary">{{ $import->lines_total }} linhas</span>
            <span class="badge text-bg-success">{{ $import->matched_count }} conciliadas</span>
            <span class="badge text-bg-primary">{{ $import->imported_count }} importadas</span>
            @if(($import->netted_count ?? 0) > 0)
                <span class="badge text-bg-dark" title="Compra + estorno no mesmo dia e valor — não importar">
                    {{ $import->netted_count }} estornados (ocultos)
                </span>
            @endif
        </div>
    </div>
    <div class="col-md-4 text-md-end">
        <form action="{{ route('statements.confirm-all', $import) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-success btn-sm">Confirmar todas sugestões</button>
        </form>
        <form action="{{ route('statements.import-unmatched', $import) }}" method="POST" class="d-inline"
            onsubmit="return confirm('Criar transações para todas as linhas sem correspondência?');">
            @csrf
            <button type="submit" class="btn btn-outline-primary btn-sm">Importar não conciliadas</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body table-responsive p-0">
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Descrição</th>
                    <th>Tipo</th>
                    <th class="text-end">Valor</th>
                    <th>Status</th>
                    <th>Correspondência</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($import->lines as $line)
                    <tr class="@if($line->match_status === 'matched') table-success @elseif($line->match_status === 'suggested') table-warning @endif">
                        <td class="text-nowrap">{{ $line->transaction_date->format('d/m/Y H:i') }}</td>
                        <td>
                            {{ $line->description }}
                            @if($line->counterparty)<br><small class="text-muted">{{ $line->counterparty }}</small>@endif
                        </td>
                        <td><span class="badge text-bg-{{ $line->type->value === 'income' ? 'success' : 'danger' }}">{{ $line->type->value }}</span></td>
                        <td class="text-end fw-semibold">R$ {{ number_format($line->amount, 2, ',', '.') }}</td>
                        <td>
                            @php
                                $labels = [
                                    'unmatched' => ['Sem match', 'secondary'],
                                    'suggested' => ['Sugerido', 'warning'],
                                    'matched' => ['Conciliado', 'success'],
                                    'imported' => ['Importado', 'primary'],
                                    'skipped' => ['Ignorado', 'dark'],
                                ];
                                [$lbl, $color] = $labels[$line->match_status] ?? [$line->match_status, 'secondary'];
                            @endphp
                            <span class="badge text-bg-{{ $color }}">{{ $lbl }}</span>
                            @if($line->match_score)<small class="text-muted">({{ $line->match_score }}%)</small>@endif
                        </td>
                        <td>
                            @if($line->transaction)
                                <a href="{{ route('transactions.edit', $line->transaction) }}">#{{ $line->transaction->id }}</a>
                                {{ Str::limit($line->transaction->description, 40) }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @if($line->match_status === 'suggested' && $line->transaction_id)
                                <form action="{{ route('statements.lines.match', [$import, $line]) }}" method="POST" class="d-inline">@csrf
                                    <button class="btn btn-xs btn-success btn-sm">Confirmar</button>
                                </form>
                            @endif
                            @if(in_array($line->match_status, ['unmatched', 'suggested']))
                                <form action="{{ route('statements.lines.import', [$import, $line]) }}" method="POST" class="d-inline">@csrf
                                    <button class="btn btn-xs btn-outline-primary btn-sm">Criar tx</button>
                                </form>
                                <form action="{{ route('statements.lines.skip', [$import, $line]) }}" method="POST" class="d-inline">@csrf
                                    <button class="btn btn-xs btn-outline-secondary btn-sm">Ignorar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            Nenhuma linha pendente.
                            @if(($import->netted_count ?? 0) > 0)
                                {{ $import->netted_count }} lançamento(s) ocultados (compra+estorno ou estornos Uber duplicados no mesmo dia).
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<a href="{{ route('statements.index') }}" class="btn btn-secondary mt-2">Voltar</a>
@endsection
