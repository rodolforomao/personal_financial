@php
    use App\Core\Enums\FundingSource;
    use App\Core\Enums\PaymentMethod;
    $fundingValue = old('funding_source', isset($transaction) ? $transaction->funding_source : null);
    $paymentValue = old('payment_method', isset($transaction) ? $transaction->payment_method : null);
@endphp
<div class="col-md-6 mb-3">
    <label class="form-label">Fonte do recurso <span class="text-muted fw-normal">(opcional)</span></label>
    <select name="funding_source" class="form-select">
        <option value="">— Não informado —</option>
        @foreach(FundingSource::cases() as $source)
            <option value="{{ $source->value }}" @selected($fundingValue === $source->value)>{{ $source->label() }}</option>
        @endforeach
    </select>
    <small class="text-muted">Onde o dinheiro está: Nubank, Inter, Sideswap, etc.</small>
</div>
<div class="col-md-6 mb-3">
    <label class="form-label">Meio de pagamento <span class="text-muted fw-normal">(opcional)</span></label>
    <select name="payment_method" class="form-select">
        <option value="">— Não informado —</option>
        @foreach(PaymentMethod::cases() as $method)
            <option value="{{ $method->value }}" @selected($paymentValue === $method->value)>{{ $method->label() }}</option>
        @endforeach
    </select>
    <small class="text-muted">PIX, TED, boleto, cartão, cripto, etc.</small>
</div>
