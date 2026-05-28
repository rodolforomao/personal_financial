@php
    $isArray = is_array($milestone);
    $id = $isArray ? ($milestone['id'] ?? null) : ($milestone?->id);
    $name = $isArray ? ($milestone['name'] ?? '') : ($milestone?->name ?? '');
    $weight = $isArray ? ($milestone['weight_percent'] ?? '') : ($milestone?->weight_percent ?? '');
    $dueAt = $isArray
        ? ($milestone['due_at'] ?? '')
        : ($milestone?->due_at?->format('Y-m-d') ?? '');
    $completed = $isArray
        ? filter_var($milestone['is_completed'] ?? false, FILTER_VALIDATE_BOOLEAN)
        : (bool) ($milestone?->is_completed ?? false);
@endphp
<tr class="milestone-row">
    <td>
        @if($id)
            <input type="hidden" name="milestones[{{ $index }}][id]" value="{{ $id }}">
        @endif
        <input type="text" name="milestones[{{ $index }}][name]" class="form-control form-control-sm"
            placeholder="Ex.: Proposta aprovada" value="{{ $name }}">
    </td>
    <td>
        <input type="number" step="0.01" min="0" max="100" name="milestones[{{ $index }}][weight_percent]"
            class="form-control form-control-sm" value="{{ $weight }}">
    </td>
    <td>
        <input type="date" name="milestones[{{ $index }}][due_at]" class="form-control form-control-sm"
            value="{{ $dueAt }}">
    </td>
    <td class="text-center">
        <input type="hidden" name="milestones[{{ $index }}][is_completed]" value="0">
        <input type="checkbox" class="form-check-input" name="milestones[{{ $index }}][is_completed]" value="1"
            @checked($completed)>
    </td>
    <td class="text-end">
        <button type="button" class="btn btn-sm btn-outline-danger remove-milestone" title="Remover">
            <i class="bi bi-trash"></i>
        </button>
    </td>
</tr>
