@extends('layouts.guest')

@section('content')
@php
    $pixPayload = $payment?->status === 'pending' ? data_get($payment?->metadata, 'liquidx.qr_code') : null;
    $transferStatus = data_get($payment?->metadata, 'liquidx.status');
    $expirationTime = data_get($payment?->metadata, 'liquidx.expiration_time') ?? data_get($payment?->metadata, 'liquidx.expires_at');
    $expirationLabel = null;

    if ($expirationTime) {
        try {
            $expirationLabel = \Illuminate\Support\Carbon::parse($expirationTime)
                ->timezone(config('app.timezone'))
                ->format('d/m/Y H:i');
        } catch (\Throwable) {
            $expirationLabel = null;
        }
    }
@endphp

<p class="login-box-msg">Cadastro aguardando liberação</p>

<div class="alert alert-warning small">
    Olá, {{ $user->name }}. Seu acesso ainda não está ativo.
</div>

<div class="card border-0 bg-body-tertiary mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-3">
            <div>
                <div class="fw-semibold">{{ $profile->name }}</div>
                <div class="text-muted small">{{ $profile->description }}</div>
            </div>
            <span class="badge text-bg-primary">{{ $profile->monthlyPriceLabel() }}/mês</span>
        </div>
    </div>
</div>

<p class="text-muted small">
    Depois que o pagamento for confirmado, sua conta será liberada automaticamente. Um administrador também pode liberar
    seu acesso manualmente para casos específicos.
</p>

@if($pixPayload)
    <div class="card border-success mb-3">
        <div class="card-body text-center">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold">PIX gerado</span>
                <span class="badge text-bg-warning">{{ in_array($transferStatus, ['paid', 'depix_sent', 'delayed'], true) ? 'Pago' : 'Aguardando pagamento' }}</span>
            </div>

            <img
                src="{{ route('subscription.payment.qr-code', $payment) }}"
                alt="QR code PIX para pagamento"
                class="img-fluid border rounded p-2 bg-white mb-3"
                width="280"
                height="280"
            >

            @if($expirationLabel)
                <p class="text-muted small mb-2">Válido até {{ $expirationLabel }}.</p>
            @endif

            <label for="pix-payload" class="form-label small text-muted">PIX copia e cola</label>
            <textarea id="pix-payload" class="form-control form-control-sm mb-2" rows="3" readonly>{{ $pixPayload }}</textarea>
            <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-2" data-copy-pix>
                Copiar código PIX
            </button>

            <form action="{{ route('subscription.payment.check') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-success w-100">
                    Já paguei, verificar status
                </button>
            </form>
        </div>
    </div>
@endif

@if(! $liquidxConfigured)
    <div class="alert alert-secondary small">
        Pagamento online indisponível no momento. A integração Liquidx precisa ser configurada no servidor.
    </div>
@elseif(! $pixPayload)
    <form action="{{ route('subscription.payment.store') }}" method="POST" class="mb-2">
        @csrf
        <button type="submit" class="btn btn-primary w-100">
            Pagar agora
        </button>
    </form>
@endif

<form action="{{ route('logout') }}" method="POST">
    @csrf
    <button type="submit" class="btn btn-outline-secondary w-100">Sair</button>
</form>

@if($pixPayload)
    <script>
        document.querySelector('[data-copy-pix]')?.addEventListener('click', async () => {
            const payload = document.getElementById('pix-payload')?.value || '';

            try {
                await navigator.clipboard.writeText(payload);
            } catch (error) {
                document.getElementById('pix-payload')?.select();
                document.execCommand('copy');
            }
        });
    </script>
@endif
@endsection
