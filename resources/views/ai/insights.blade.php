@extends('layouts.adminlte')

@section('title', 'Insights IA')
@section('page_title', 'Insights de IA')
@section('breadcrumb')<li class="breadcrumb-item active">Insights IA</li>@endsection

@section('content')
<div class="mb-3">
    <form action="{{ route('ai.analyze') }}" method="POST" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-primary"><i class="bi bi-cpu"></i> Executar análise do ecossistema</button>
    </form>
</div>
@foreach($insights as $insight)
    <div class="card card-outline card-{{ $insight->severity->value === 'critical' ? 'danger' : 'info' }} mb-3">
        <div class="card-header">
            <h3 class="card-title">{{ $insight->title }}</h3>
            <span class="badge text-bg-secondary float-end">{{ $insight->type }}</span>
        </div>
        <div class="card-body">
            <p>{{ $insight->summary }}</p>
            @if($insight->suggested_actions)
                <ul class="mb-0">
                    @foreach($insight->suggested_actions as $action)
                        <li>{{ is_array($action) ? ($action['text'] ?? json_encode($action)) : $action }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endforeach
<div>{{ $insights->links() }}</div>
@endsection
