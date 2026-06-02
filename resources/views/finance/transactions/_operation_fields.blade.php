@php
    $selectedOperationId = old('operation_id', $preselectedOperationId ?? (isset($transaction) ? $transaction->operation_id : null));
    $selectedUnitId = old('operation_unit_id', $preselectedUnitId ?? (isset($transaction) ? $transaction->operation_unit_id : null));
    $unitsJson = $operations->mapWithKeys(fn ($op) => [
        $op->id => $op->activeUnits->map(fn ($u) => ['id' => $u->id, 'label' => $u->displayName()])->values(),
    ]);
@endphp
<div class="col-md-6 mb-3">
    <label class="form-label">Operação <span class="text-muted fw-normal">(opcional)</span></label>
    <select name="operation_id" id="tx-operation-id" class="form-select">
        <option value="">— Dashboard principal —</option>
        @foreach($operations as $op)
            <option value="{{ $op->id }}" @selected($selectedOperationId == $op->id)>
                {{ $op->name }}@if($op->company) ({{ $op->company->name }})@endif
            </option>
        @endforeach
    </select>
    <small class="text-muted">Negócios separados (ex. Residencial Oliveiras) não entram no dashboard geral.</small>
</div>
<div class="col-md-6 mb-3">
    <label class="form-label">Unidade</label>
    <select name="operation_unit_id" id="tx-operation-unit-id" class="form-select">
        <option value="">— Operação inteira ou geral —</option>
    </select>
</div>
@push('scripts')
<script>
(function () {
    const unitsByOperation = @json($unitsJson);
    const opSelect = document.getElementById('tx-operation-id');
    const unitSelect = document.getElementById('tx-operation-unit-id');
    const preselectedUnit = @json($selectedUnitId);

    function refreshUnits() {
        const opId = opSelect?.value;
        unitSelect.innerHTML = '<option value="">— Operação inteira —</option>';
        if (!opId || !unitsByOperation[opId]) return;
        unitsByOperation[opId].forEach(function (u) {
            const opt = document.createElement('option');
            opt.value = u.id;
            opt.textContent = u.label;
            if (String(preselectedUnit) === String(u.id)) opt.selected = true;
            unitSelect.appendChild(opt);
        });
    }

    opSelect?.addEventListener('change', function () {
        unitSelect.value = '';
        refreshUnits();
    });
    refreshUnits();
})();
</script>
@endpush
