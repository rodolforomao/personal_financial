<div class="card mb-3">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0 small"><i class="bi bi-funnel"></i> Filtros do relatório</h3>
        @if($filtersActive ?? false)
            <span class="badge text-bg-primary">Filtros ativos</span>
        @endif
    </div>
    <div class="card-body py-2">
        <form method="GET" action="{{ route('reports.index') }}" id="report-filter-form">
            <div class="row g-2 align-items-end">
                <div class="col-md-2 col-lg-2">
                    <label class="form-label small mb-0">Data de</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2 col-lg-2">
                    <label class="form-label small mb-0">Data até</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-4 col-lg-3">
                    <label class="form-label small mb-0">Categoria</label>
                    <select name="category_id" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-lg-3">
                    <label class="form-label small mb-0">Empresa</label>
                    <select name="company_id" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        @foreach($companies as $co)
                            <option value="{{ $co->id }}" @selected(request('company_id') == $co->id)>{{ $co->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-lg-3">
                    <label class="form-label small mb-0">Operação</label>
                    <select name="operation_id" id="report-filter-operation-id" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        @foreach($operations as $op)
                            <option value="{{ $op->id }}" @selected(request('operation_id') == $op->id)>{{ $op->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-lg-3">
                    <label class="form-label small mb-0">Unidade (apto)</label>
                    <select name="operation_unit_id" id="report-filter-operation-unit-id" class="form-select form-select-sm"
                            @disabled(!request('operation_id'))>
                        <option value="">Todas</option>
                        @foreach($operationUnits as $unit)
                            <option value="{{ $unit->id }}" @selected(request('operation_unit_id') == $unit->id)>
                                {{ $unit->displayName() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-lg-2">
                    <label class="form-label small mb-0">Tipo</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="expense" @selected(request('type') === 'expense')>Despesa</option>
                        <option value="income" @selected(request('type') === 'income')>Receita</option>
                        <option value="transfer" @selected(request('type') === 'transfer')>Transferência</option>
                    </select>
                </div>
                <div class="col-md-2 col-lg-2">
                    <label class="form-label small mb-0">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        @foreach(\App\Core\Enums\TransactionStatus::cases() as $st)
                            <option value="{{ $st->value }}" @selected(request('status') === $st->value)>{{ $st->value }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-lg-2">
                    <label class="form-label small mb-0">Fonte</label>
                    <select name="funding_source" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        @foreach(\App\Core\Enums\FundingSource::cases() as $fs)
                            <option value="{{ $fs->value }}" @selected(request('funding_source') === $fs->value)>{{ $fs->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-lg-2">
                    <label class="form-label small mb-0">Meio de pagamento</label>
                    <select name="payment_method" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        @foreach(\App\Core\Enums\PaymentMethod::cases() as $pm)
                            <option value="{{ $pm->value }}" @selected(request('payment_method') === $pm->value)>{{ $pm->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-lg-3 d-flex gap-1 align-items-end flex-wrap">
                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Aplicar</button>
                    <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary">Limpar</a>
                </div>
            </div>
            <p class="text-muted small mb-0 mt-2">
                Sem filtros: consolida todo o workspace (confirmados e conciliados). Use operação, datas ou categoria para refinar.
                Os botões <strong>Baixar XLSX/PDF</strong> usam os filtros aplicados na página (aplique antes de exportar).
            </p>
        </form>
    </div>
</div>
