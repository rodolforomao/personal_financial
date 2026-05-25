@php
    $withCheckboxes = $withCheckboxes ?? false;
    $showOperation = $showOperation ?? true;
@endphp
<div class="card">
    <div class="card-body table-responsive p-0">
        <table class="table table-hover table-sm mb-0">
            <thead>
                <tr>
                    @if($withCheckboxes)
                        <th style="width:2rem"><input type="checkbox" id="hygiene-select-all" title="Selecionar página"></th>
                    @endif
                    <th>Data</th>
                    @if($showOperation)<th>Operação</th>@endif
                    <th>Descrição</th>
                    <th>Tipo</th>
                    <th class="text-end">Valor</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $tx)
                    <tr>
                        @if($withCheckboxes)
                            <td><input type="checkbox" class="hygiene-tx-checkbox" name="ids[]" value="{{ $tx->id }}" form="hygiene-bulk-form"></td>
                        @endif
                        <td>{{ $tx->transaction_date->format('d/m/Y') }}</td>
                        @if($showOperation)<td>{{ $tx->operation?->name ?? '—' }}</td>@endif
                        <td>{{ Str::limit($tx->description, 50) }}</td>
                        <td><span class="badge text-bg-{{ $tx->type->value === 'income' ? 'success' : 'danger' }}">{{ $tx->type->value }}</span></td>
                        <td class="text-end">R$ {{ number_format($tx->amount, 2, ',', '.') }}</td>
                        <td class="text-end"><a href="{{ route('transactions.edit', $tx) }}" class="btn btn-xs btn-outline-secondary btn-sm">Editar</a></td>
                    </tr>
                @empty
                    <tr><td colspan="{{ ($withCheckboxes ? 1 : 0) + ($showOperation ? 6 : 5) }}" class="text-center text-muted py-3">{{ $emptyMessage }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($transactions->hasPages())
        <div class="card-footer">{{ $transactions->links() }}</div>
    @endif
</div>
