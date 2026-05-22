@extends('layouts.adminlte')

@section('title', 'Usuários da plataforma')
@section('page_title', 'Usuários — IA interna')
@section('breadcrumb')
    <li class="breadcrumb-item active">Administração</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Usuários internos (sem cobrança de IA)</h3>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Usuários <strong>internos</strong> podem usar a IA da plataforma (<code>OPENAI_API_KEY</code> do servidor)
            sem aceitar cobrança. Demais usuários precisam aceitar explicitamente em Configuração IA.
        </p>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Interno</th>
                        <th class="text-end">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $u)
                        <tr>
                            <td>{{ $u->name }}</td>
                            <td>{{ $u->email }}</td>
                            <td>
                                @if($u->is_platform_internal)
                                    <span class="badge text-bg-success">Interno — sem cobrança</span>
                                @else
                                    <span class="badge text-bg-secondary">Cliente</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <form action="{{ route('admin.users.toggle-internal', $u) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm {{ $u->is_platform_internal ? 'btn-outline-warning' : 'btn-outline-success' }}">
                                        @if($u->is_platform_internal)
                                            Remover interno
                                        @else
                                            Ativar interno
                                        @endif
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $users->links() }}
    </div>
</div>
@endsection
