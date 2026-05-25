@if($transaction->fundingSourceLabel() || $transaction->paymentMethodLabel())
    <div class="small mt-1">
        @if($transaction->fundingSourceLabel())
            <span class="badge text-bg-light text-dark border me-1">
                <i class="bi bi-bank"></i> {{ $transaction->fundingSourceLabel() }}
            </span>
        @endif
        @if($transaction->paymentMethodLabel())
            <span class="badge text-bg-secondary">
                {{ $transaction->paymentMethodLabel() }}
            </span>
        @endif
    </div>
@endif
