@extends('layouts.adminlte')

@section('title', 'Configurações')
@section('page_title', 'Configurações da plataforma')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">Administração</a></li>
    <li class="breadcrumb-item active">Configurações</li>
@endsection

@php
    $sourceBadge = fn (string $field) => ($liquidx[$field.'_from_database'] ?? false)
        ? '<span class="badge text-bg-primary">Painel</span>'
        : '<span class="badge text-bg-secondary">.env / padrão</span>';
@endphp

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0">
                    <i class="bi bi-credit-card-2-front"></i>
                    Liquidx.pro
                </h3>
            </div>
            <form action="{{ route('admin.settings.update') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="card-body">
                    <div class="alert alert-info py-2">
                        Configure aqui a cobrança PIX usada na tela de assinatura pendente. Os campos sensíveis ficam criptografados no banco.
                    </div>

                    <div class="mb-3">
                        <label class="form-label">URL base da API</label>
                        <div class="input-group">
                            <input type="url"
                                name="base_url"
                                class="form-control @error('base_url') is-invalid @enderror"
                                value="{{ old('base_url', $liquidx['base_url']) }}"
                                placeholder="https://liquidx.pro/api"
                                required>
                            <span class="input-group-text">{!! $sourceBadge('base_url') !!}</span>
                            @error('base_url')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Código integrado</label>
                        <div class="input-group">
                            <input type="text"
                                name="integrated_code"
                                class="form-control @error('integrated_code') is-invalid @enderror"
                                value="{{ old('integrated_code', $liquidx['integrated_code']) }}"
                                autocomplete="off"
                                required>
                            <span class="input-group-text">{!! $sourceBadge('integrated_code') !!}</span>
                            @error('integrated_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-text">Enviado como <code>code</code> ao criar a cobrança integrada.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">API key</label>
                        <input type="password"
                            name="api_key"
                            class="form-control @error('api_key') is-invalid @enderror"
                            placeholder="{{ $liquidx['has_api_key'] ? 'Chave salva. Deixe em branco para manter.' : 'Bearer token da Liquidx, se exigido' }}"
                            autocomplete="off">
                        @error('api_key')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="d-flex flex-wrap gap-3 mt-2">
                            @if($liquidx['has_api_key'])
                                <small class="text-success"><i class="bi bi-check-circle"></i> API key configurada</small>
                            @endif
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="clear_api_key" value="1" id="clear-api-key">
                                <label class="form-check-label small" for="clear-api-key">Remover chave salva</label>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Path de criação</label>
                            <input type="text"
                                name="integrated_payment_path"
                                class="form-control @error('integrated_payment_path') is-invalid @enderror"
                                value="{{ old('integrated_payment_path', $liquidx['integrated_payment_path']) }}"
                                required>
                            @error('integrated_payment_path')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Path de status</label>
                            <input type="text"
                                name="integrated_payment_status_path"
                                class="form-control @error('integrated_payment_status_path') is-invalid @enderror"
                                value="{{ old('integrated_payment_status_path', $liquidx['integrated_payment_status_path']) }}"
                                required>
                            @error('integrated_payment_status_path')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Thirdwallet</label>
                            <input type="text"
                                name="thirdwallet"
                                class="form-control @error('thirdwallet') is-invalid @enderror"
                                value="{{ old('thirdwallet', $liquidx['thirdwallet']) }}"
                                autocomplete="off">
                            @error('thirdwallet')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Telefone padrão do pagador</label>
                            <input type="text"
                                name="default_payer_phone"
                                class="form-control @error('default_payer_phone') is-invalid @enderror"
                                value="{{ old('default_payer_phone', $liquidx['default_payer_phone']) }}"
                                placeholder="+55 11 99999-9999">
                            @error('default_payer_phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Timeout HTTP (s)</label>
                            <input type="number"
                                name="timeout"
                                class="form-control @error('timeout') is-invalid @enderror"
                                value="{{ old('timeout', $liquidx['timeout']) }}"
                                min="1"
                                max="120"
                                required>
                            @error('timeout')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Segredo do webhook</label>
                            <input type="password"
                                name="webhook_secret"
                                class="form-control @error('webhook_secret') is-invalid @enderror"
                                placeholder="{{ $liquidx['has_webhook_secret'] ? 'Segredo salvo. Deixe em branco para manter.' : 'Opcional' }}"
                                autocomplete="off">
                            @error('webhook_secret')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="d-flex flex-wrap gap-3 mt-2">
                                @if($liquidx['has_webhook_secret'])
                                    <small class="text-success"><i class="bi bi-check-circle"></i> Webhook protegido por segredo</small>
                                @endif
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="clear_webhook_secret" value="1" id="clear-webhook-secret">
                                    <label class="form-check-label small" for="clear-webhook-secret">Remover segredo salvo</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i>
                        Salvar Liquidx.pro
                    </button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Voltar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card card-outline {{ $liquidx['configured'] ? 'card-success' : 'card-warning' }}">
            <div class="card-header"><h3 class="card-title">Status</h3></div>
            <div class="card-body small">
                @if($liquidx['configured'])
                    <p class="text-success fw-semibold mb-2">
                        <i class="bi bi-check-circle"></i>
                        Liquidx pronta para gerar PIX.
                    </p>
                @else
                    <p class="text-warning fw-semibold mb-2">
                        <i class="bi bi-exclamation-triangle"></i>
                        Informe URL base e código integrado.
                    </p>
                @endif

                <p class="mb-2">
                    Criação:
                    <code>{{ rtrim((string) $liquidx['base_url'], '/') }}{{ $liquidx['integrated_payment_path'] }}</code>
                </p>
                <p class="mb-2">
                    Status:
                    <code>{{ rtrim((string) $liquidx['base_url'], '/') }}{{ $liquidx['integrated_payment_status_path'] }}</code>
                </p>
                <p class="mb-0">
                    Webhook:
                    <code>{{ $liquidx['webhook_url'] }}</code>
                </p>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Prioridade</h3></div>
            <div class="card-body small">
                <p>Valores salvos nesta tela têm prioridade sobre o <code>.env</code>.</p>
                <p class="mb-0">Campos sensíveis não são exibidos depois de salvos; preencha novamente apenas para trocar.</p>
            </div>
        </div>
    </div>
</div>
@endsection
