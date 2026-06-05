@extends('layouts.adminlte')

@section('title', 'Salário CLT')
@section('page_title', 'Salário CLT')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('companies.index') }}">Empresas</a></li>
    <li class="breadcrumb-item active">CLT</li>
@endsection

@section('content')
<p class="text-muted">
    Pagamentos CLT: cadastre valor <strong>bruto</strong> (opcional) e <strong>líquido</strong> de referência.
    Todo mês, após o pagamento, confirme o <strong>líquido real</strong>, a <strong>data em que caiu na conta</strong> e, se quiser, o holerite/comprovante.
</p>

@if($pendingPeriods->isNotEmpty())
<div class="card card-warning card-outline mb-4">
    <div class="card-header"><h3 class="card-title"><i class="bi bi-calendar-check"></i> Confirmar este mês</h3></div>
    <div class="card-body p-0">
        @foreach($pendingPeriods as $period)
            @php
                $salary = $period->cltSalary;
                $month = $period->reference_month->translatedFormat('F/Y');
            @endphp
            <form action="{{ route('clt-salaries.periods.confirm', $period) }}" method="POST" enctype="multipart/form-data"
                class="border-bottom p-3">
                @csrf
                <div class="row g-3">
                    <div class="col-md-12 col-lg-4">
                        <strong>{{ $salary->company->name }}</strong>
                        <div class="small text-muted">
                            Referência {{ $month }} · dia usual {{ $salary->payment_day }}
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-2">
                        <label class="form-label small mb-0 fw-semibold">Data real do pagamento</label>
                        <input type="date" name="transaction_date" class="form-control form-control-sm @error('transaction_date') is-invalid @enderror"
                            value="{{ old('transaction_date', $suggestedPayDates[$period->id] ?? now()->toDateString()) }}" required>
                        @error('transaction_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="text-muted">Dia em que caiu na conta</small>
                    </div>
                    <div class="col-md-4 col-lg-2">
                        <label class="form-label small mb-0">Bruto (opcional)</label>
                        <input type="number" step="0.01" name="gross_amount" class="form-control form-control-sm"
                            value="{{ old('gross_amount', $period->gross_amount) }}" placeholder="—">
                    </div>
                    <div class="col-md-4 col-lg-2">
                        <label class="form-label small mb-0 fw-semibold">Líquido (R$)</label>
                        <input type="number" step="0.01" name="net_amount" class="form-control form-control-sm @error('net_amount') is-invalid @enderror"
                            required value="{{ old('net_amount', $period->net_amount) }}">
                        @error('net_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12 col-lg-2 d-flex flex-column gap-1 justify-content-end">
                        <button type="submit" class="btn btn-success btn-sm w-100">
                            <i class="bi bi-check-lg"></i> Confirmar
                        </button>
                        <button type="submit" formaction="{{ route('clt-salaries.periods.skip', $period) }}"
                            formmethod="POST" class="btn btn-outline-secondary btn-sm w-100"
                            onclick="return confirm('Ignorar confirmação deste mês?');">
                            Ignorar mês
                        </button>
                    </div>
                    <div class="col-12">
                        <label class="form-label small mb-0">
                            <i class="bi bi-paperclip"></i> Comprovante / holerite (opcional)
                        </label>
                        <input type="file" name="receipts[]" class="form-control form-control-sm"
                            accept=".pdf,.jpg,.jpeg,.png,.webp" multiple>
                        <small class="text-muted">PDF ou imagem — também pode anexar depois na transação gerada</small>
                    </div>
                </div>
            </form>
        @endforeach
    </div>
</div>
@endif

<div class="row">
    <div class="col-lg-5">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title">Cadastrar / atualizar vínculo CLT</h3></div>
            <form action="{{ route('clt-salaries.store') }}" method="POST">
                @csrf
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Empregador</label>
                        <select name="company_id" class="form-select" required>
                            <option value="">— Selecione —</option>
                            @foreach($employers as $emp)
                                <option value="{{ $emp->id }}" @selected(old('company_id') == $emp->id)>
                                    {{ $emp->name }} ({{ $emp->type->label() }})
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Tipo <em>Empregador</em> em Empresas. Se já existir CLT para a empresa, os valores serão atualizados.</small>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Bruto referência (opcional)</label>
                            <input type="number" step="0.01" name="gross_amount" class="form-control"
                                value="{{ old('gross_amount') }}" placeholder="Ex.: 8000">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">Líquido referência</label>
                            <input type="number" step="0.01" name="net_amount" class="form-control" required
                                value="{{ old('net_amount') }}" placeholder="Ex.: 6500">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Dia usual do pagamento</label>
                        <input type="number" name="payment_day" class="form-control" min="1" max="28"
                            value="{{ old('payment_day', 5) }}" required>
                        <small class="text-muted">Sugestão ao confirmar o mês; na confirmação você informa a data real.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição no lançamento</label>
                        <input type="text" name="description_template" class="form-control"
                            value="{{ old('description_template', 'Salário CLT') }}" maxlength="120">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="clt-active" checked>
                        <label class="form-check-label" for="clt-active">Ativo (gera lembrete mensal)</label>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">Salvar vínculo CLT</button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <h3 class="card-title mb-0">Vínculos cadastrados</h3>
                <input type="search" id="clt-search" class="form-control form-control-sm ms-auto" style="max-width:220px" placeholder="Buscar...">
            </div>
            <div class="card-body table-responsive p-0">
                <table id="clt-salaries-table" class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Empregador</th>
                            <th>Bruto</th>
                            <th>Líquido ref.</th>
                            <th>Dia</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($salaries as $s)
                            <tr>
                                <td>
                                    <a href="{{ route('companies.edit', $s->company_id) }}">{{ $s->company->name }}</a>
                                    @unless($s->is_active)<span class="badge text-bg-secondary">inativo</span>@endunless
                                </td>
                                <td>{{ $s->gross_amount ? 'R$ '.number_format($s->gross_amount, 2, ',', '.') : '—' }}</td>
                                <td>R$ {{ number_format($s->net_amount, 2, ',', '.') }}</td>
                                <td>{{ $s->payment_day }}</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse"
                                        data-bs-target="#edit-clt-{{ $s->id }}">Editar</button>
                                </td>
                            </tr>
                            <tr class="collapse" id="edit-clt-{{ $s->id }}">
                                <td colspan="5" class="bg-light">
                                    <form action="{{ route('clt-salaries.update', $s) }}" method="POST" class="p-2">
                                        @csrf
                                        @method('PUT')
                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-3">
                                                <label class="form-label small">Bruto</label>
                                                <input type="number" step="0.01" name="gross_amount" class="form-control form-control-sm"
                                                    value="{{ $s->gross_amount }}">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small">Líquido</label>
                                                <input type="number" step="0.01" name="net_amount" class="form-control form-control-sm"
                                                    value="{{ $s->net_amount }}" required>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small">Dia</label>
                                                <input type="number" name="payment_day" class="form-control form-control-sm"
                                                    value="{{ $s->payment_day }}" min="1" max="28" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small">Descrição</label>
                                                <input type="text" name="description_template" class="form-control form-control-sm"
                                                    value="{{ $s->description_template }}">
                                            </div>
                                            <div class="col-md-1">
                                                <button type="submit" class="btn btn-sm btn-primary w-100">OK</button>
                                            </div>
                                        </div>
                                        <input type="hidden" name="company_id" value="{{ $s->company_id }}">
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted text-center py-4">Nenhum vínculo CLT. Cadastre o empregador à esquerda.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="alert alert-info small mt-3 mb-0">
            <strong>Categoria:</strong> lançamentos confirmados usam <em>Salário CLT</em> (não “Outras receitas”).
            Migre empresas de tipo <em>Me paga</em> para <em>Empregador</em> quando for vínculo CLT.
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const input = document.getElementById('clt-search');
    const table = document.getElementById('clt-salaries-table');
    if (!input || !table) return;
    input.addEventListener('input', function () {
        const q = this.value.toLowerCase();
        table.querySelectorAll('tbody tr:not(.collapse)').forEach(function (row) {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
})();
</script>
@endpush
