@extends('layouts.adminlte')

@section('title', 'Empresas')
@section('page_title', 'Empresas')
@section('breadcrumb')<li class="breadcrumb-item active">Empresas</li>@endsection

@section('content')
<div class="row mb-3">
    @foreach(\App\Core\Enums\CompanyType::forForm() as $type)
        <div class="col-md-4">
            <div class="info-box">
                <span class="info-box-icon text-bg-primary"><i class="bi bi-building"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ $type->label() }}</span>
                    <span class="info-box-number">{{ $byType[$type->value] ?? $byType[\App\Core\Enums\CompanyType::Client->value] ?? 0 }}</span>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Cadastro de empresas</h3>
        <div class="card-tools">
            <a href="{{ route('companies.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nova empresa</a>
        </div>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Tipo</th>
                    <th>Participação</th>
                    <th>Receita esperada/mês</th>
                </tr>
            </thead>
            <tbody>
                @forelse($companies as $c)
                    <tr>
                        <td>{{ $c->name }}</td>
                        <td>
                            <span class="badge text-bg-{{ match($c->type->value) { 'own' => 'primary', 'partner' => 'info', 'payer', 'client' => 'success', default => 'secondary' } }}">
                                {{ $c->type->label() }}
                            </span>
                        </td>
                        <td>{{ $c->partnership_share ? $c->partnership_share.'%' : '—' }}</td>
                        <td>R$ {{ number_format($c->expected_monthly_revenue ?? 0, 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted">Nenhuma empresa cadastrada</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $companies->links() }}</div>
</div>
@endsection
