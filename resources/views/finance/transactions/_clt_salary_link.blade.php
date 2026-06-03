@if($transaction->type->value === 'income')
@php
    $cltPeriod = $transaction->cltSalaryPeriod;
    $hasLink = $cltPeriod !== null;
    $cardColor = $hasLink ? 'success' : 'secondary';

    // Períodos vinculáveis: pendentes ou confirmados sem transação
    $linkablePeriods = collect();
    if (!$hasLink) {
        foreach ($cltSalaries as $salary) {
            $periods = \Modules\Finance\Infrastructure\Models\CltSalaryPeriod::query()
                ->where('clt_salary_id', $salary->id)
                ->where(function ($q) {
                    $q->where('status', 'pending')
                      ->orWhere(function ($q2) {
                          $q2->where('status', 'confirmed')->whereNull('transaction_id');
                      });
                })
                ->orderByDesc('reference_month')
                ->get();
            foreach ($periods as $p) {
                $linkablePeriods->push([
                    'id' => $p->id,
                    'label' => $salary->company->name.' — '.\Carbon\Carbon::parse($p->reference_month)->translatedFormat('F/Y'),
                    'status' => $p->status,
                ]);
            }
        }
    }
@endphp
<div class="card card-outline card-{{ $cardColor }} mt-3">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-person-badge"></i> Salário CLT
        </h3>
    </div>
    <div class="card-body">

        @if($hasLink)
            @php
                $salary = $cltPeriod->cltSalary;
                $refMonth = \Carbon\Carbon::parse($cltPeriod->reference_month)->translatedFormat('F/Y');
            @endphp
            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                <div>
                    <p class="mb-1">
                        <strong>{{ $salary->company->name }}</strong>
                        <span class="badge text-bg-success ms-1">
                            <i class="bi bi-check-circle-fill"></i> confirmado
                        </span>
                    </p>
                    <p class="mb-1 text-muted small">
                        Referência: <strong>{{ $refMonth }}</strong>
                        @if($cltPeriod->gross_amount)
                            &nbsp;·&nbsp; Bruto: R$ {{ number_format($cltPeriod->gross_amount, 2, ',', '.') }}
                        @endif
                        &nbsp;·&nbsp; Líquido: R$ {{ number_format($cltPeriod->net_amount, 2, ',', '.') }}
                    </p>
                    <p class="mb-0 text-muted small">
                        Período #{{ $cltPeriod->id }}
                        @if($cltPeriod->confirmed_at)
                            &nbsp;·&nbsp; Confirmado em {{ $cltPeriod->confirmed_at->format('d/m/Y H:i') }}
                        @endif
                    </p>
                </div>
                <form method="POST" action="{{ route('transactions.unlink-clt', $transaction) }}"
                      onsubmit="return confirm('Desvincular este lançamento do salário CLT? O período voltará para pendente.')">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-lg"></i> Desvincular
                    </button>
                </form>
            </div>

        @elseif($cltSalaries->isEmpty())
            <p class="text-muted small mb-0">
                <i class="bi bi-info-circle"></i>
                Nenhum salário CLT cadastrado.
                <a href="{{ route('clt-salaries.index') }}">Cadastrar em Salário CLT</a>.
            </p>

        @elseif($linkablePeriods->isEmpty())
            <p class="text-muted small mb-0">
                <i class="bi bi-info-circle"></i>
                Não há períodos pendentes para vincular. Todos os salários CLT já têm transação associada ou não há períodos abertos.
            </p>

        @else
            <p class="text-muted small mb-3">
                Vincule este lançamento a um período de salário CLT. O período ficará confirmado e
                a transação será marcada como origem <code>clt_salary</code>.
            </p>
            <form method="POST" action="{{ route('transactions.link-clt', $transaction) }}">
                @csrf
                <div class="row g-2 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold">Período de competência</label>
                        <select name="clt_salary_period_id"
                                class="form-select form-select-sm @error('clt_salary_period_id') is-invalid @enderror"
                                required>
                            <option value="">— Selecione o mês de referência —</option>
                            @foreach($linkablePeriods as $p)
                                <option value="{{ $p['id'] }}" @selected(old('clt_salary_period_id') == $p['id'])>
                                    {{ $p['label'] }}
                                    @if($p['status'] === 'pending') (pendente) @else (aguardando vínculo) @endif
                                </option>
                            @endforeach
                        </select>
                        @error('clt_salary_period_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-sm btn-success">
                            <i class="bi bi-person-badge"></i> Vincular ao salário CLT
                        </button>
                    </div>
                </div>
            </form>
        @endif

    </div>
</div>
@endif
