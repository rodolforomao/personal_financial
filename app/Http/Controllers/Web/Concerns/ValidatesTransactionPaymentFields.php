<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Core\Enums\FundingSource;
use App\Core\Enums\PaymentMethod;

trait ValidatesTransactionPaymentFields
{
    /**
     * @return array<string, mixed>
     */
    protected function paymentFieldRules(): array
    {
        $sources = implode(',', array_column(FundingSource::cases(), 'value'));
        $methods = implode(',', array_column(PaymentMethod::cases(), 'value'));

        return [
            'funding_source' => 'nullable|string|in:'.$sources,
            'payment_method' => 'nullable|string|in:'.$methods,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{funding_source: ?string, payment_method: ?string}
     */
    protected function normalizePaymentFields(array $validated): array
    {
        return [
            'funding_source' => ! empty($validated['funding_source']) ? $validated['funding_source'] : null,
            'payment_method' => ! empty($validated['payment_method']) ? $validated['payment_method'] : null,
        ];
    }
}
