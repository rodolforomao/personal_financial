@extends('layouts.adminlte')

@section('title', 'Documentos')
@section('page_title', 'Documentos & OCR')
@section('breadcrumb')<li class="breadcrumb-item active">OCR</li>@endsection

@section('content')
<div class="row">
    <div class="col-md-4">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title">Enviar documento</h3></div>
            <form action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="card-body">
                    <input type="file" name="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                    <small class="text-muted">PDF, JPG, PNG — processamento na fila OCR</small>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary w-100">Enviar</button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-body table-responsive p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Arquivo</th><th>Status</th><th>OCR</th><th>Data</th></tr></thead>
                    <tbody>
                        @foreach($documents as $doc)
                            <tr>
                                <td>{{ $doc->original_name }}</td>
                                <td><span class="badge text-bg-secondary">{{ $doc->status }}</span></td>
                                <td>
                                    @if($doc->ocr_result)
                                        <small>R$ {{ $doc->ocr_result['amount'] ?? '—' }}</small>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $doc->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $documents->links() }}</div>
        </div>
    </div>
</div>
@endsection
