<?php

namespace App\Services;

use App\Models\SubscriptionProfile;
use App\Models\User;
use App\Models\UserAccessPayment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class LiquidxPaymentService
{
    public const PROVIDER = 'liquidx';

    private const PAID_STATUSES = ['paid', 'depix_sent', 'delayed'];

    private const FAILED_STATUSES = ['expired', 'refunded', 'canceled', 'cancelled', 'error'];

    public function __construct(private readonly UserAccessService $accessService) {}

    public function configured(): bool
    {
        return $this->baseUrl() !== ''
            && $this->integratedCode() !== '';
    }

    public function latestPaymentFor(User $user): ?UserAccessPayment
    {
        return $user->accessPayments()
            ->where('provider', self::PROVIDER)
            ->latest()
            ->first();
    }

    public function startPixPayment(User $user, SubscriptionProfile $profile): UserAccessPayment
    {
        if (! $this->configured()) {
            throw new RuntimeException('A integração Liquidx ainda não está configurada.');
        }

        $existing = $this->latestReusablePendingPayment($user);
        if ($existing !== null) {
            return $existing;
        }

        $requestId = 'fiq-access-'.Str::uuid();
        $now = now();
        $periodStart = $user->access_expires_at?->isFuture()
            ? $user->access_expires_at->copy()
            : $now->copy();
        $periodEnd = $periodStart->copy()->addMonthNoOverflow();

        $payment = UserAccessPayment::query()->create([
            'user_id' => $user->id,
            'subscription_profile_id' => $profile->id,
            'amount_cents' => $profile->monthly_price_cents,
            'currency' => 'BRL',
            'status' => 'pending',
            'provider' => self::PROVIDER,
            'provider_payment_id' => $requestId,
            'billing_period_starts_at' => $periodStart,
            'billing_period_ends_at' => $periodEnd,
            'metadata' => [
                'liquidx' => [
                    'external_request_id' => $requestId,
                    'status' => 'creating',
                ],
            ],
        ]);

        try {
            $response = $this->client()->post(
                $this->integratedPaymentPath(),
                $this->integratedPaymentPayload($payment, $user, $profile),
            );
        } catch (Throwable $exception) {
            $this->markProviderFailure($payment, $exception->getMessage());

            throw $exception;
        }

        if ($response->failed()) {
            $this->markProviderFailure($payment, $this->providerError($response));

            throw new RuntimeException('Não foi possível gerar o QR code de pagamento.');
        }

        return $this->applyIntegratedPaymentResponse($payment, $this->json($response))->refresh();
    }

    public function refreshPaymentStatus(UserAccessPayment $payment): UserAccessPayment
    {
        if (! $this->configured()) {
            throw new RuntimeException('A integração Liquidx ainda não está configurada.');
        }

        if ($payment->provider !== self::PROVIDER || blank($payment->provider_payment_id)) {
            throw new RuntimeException('Pagamento inválido para consulta na Liquidx.');
        }

        $response = $this->client()->post($this->integratedPaymentStatusPath(), [
            'listIds' => [$payment->provider_payment_id],
        ]);

        if ($response->failed()) {
            throw new RuntimeException($this->providerError($response));
        }

        return $this->applyStatusPayload($payment, $this->firstStatusPayload($this->json($response), $payment))->refresh();
    }

    public function handleWebhook(array $payload): ?UserAccessPayment
    {
        $depixId = data_get($payload, 'depix_id')
            ?? data_get($payload, 'id')
            ?? data_get($payload, 'data.response.id')
            ?? data_get($payload, 'response.id');

        if (! is_string($depixId) || $depixId === '') {
            return null;
        }

        $payment = UserAccessPayment::query()
            ->where('provider', self::PROVIDER)
            ->where('provider_payment_id', $depixId)
            ->first();

        if (! $payment) {
            return null;
        }

        return $this->applyStatusPayload($payment, $payload, ['last_webhook_payload' => $payload])->refresh();
    }

    public function pixPayloadFor(UserAccessPayment $payment): ?string
    {
        $payload = data_get($payment->metadata, 'liquidx.qr_code');

        return is_string($payload) && $payload !== '' ? $payload : null;
    }

    private function applyIntegratedPaymentResponse(UserAccessPayment $payment, array $providerPayload): UserAccessPayment
    {
        $pixSuccess = data_get($providerPayload, 'pix.success', data_get($providerPayload, 'success', true));
        $response = data_get($providerPayload, 'pix.data.response', data_get($providerPayload, 'data.response', []));

        if ($pixSuccess === false || ! is_array($response)) {
            $message = (string) (data_get($providerPayload, 'message') ?? data_get($providerPayload, 'pix.data') ?? 'Erro retornado pela Liquidx.');
            $this->markProviderFailure($payment, $message);

            throw new RuntimeException('Não foi possível gerar o QR code de pagamento.');
        }

        $depixId = $response['id'] ?? null;
        $qrCopyPaste = $response['qrCopyPaste'] ?? null;
        $qrImageUrl = $response['qrImageUrl'] ?? null;

        if ((! is_string($depixId) || $depixId === '') && (! is_string($qrCopyPaste) || $qrCopyPaste === '')) {
            $this->markProviderFailure($payment, 'Resposta sem depix_id ou código PIX copia e cola.');

            throw new RuntimeException('Não foi possível gerar o QR code de pagamento.');
        }

        $metadata = array_replace_recursive($payment->metadata ?? [], [
            'liquidx' => array_filter([
                'depix_id' => $depixId,
                'status' => 'pending',
                'qr_code' => $qrCopyPaste,
                'qr_image_url' => $this->normalizeProviderUrl(is_string($qrImageUrl) ? $qrImageUrl : null),
                'last_provider_payload' => $providerPayload,
            ], fn ($value) => $value !== null),
        ]);

        $payment->forceFill([
            'provider_payment_id' => is_string($depixId) && $depixId !== '' ? $depixId : $payment->provider_payment_id,
            'status' => 'pending',
            'metadata' => $metadata,
        ])->save();

        return $payment;
    }

    private function applyStatusPayload(UserAccessPayment $payment, array $statusPayload, array $extraMetadata = []): UserAccessPayment
    {
        $status = Str::lower((string) (
            $statusPayload['status']
            ?? data_get($statusPayload, 'data.response.status')
            ?? data_get($statusPayload, 'response.status')
            ?? data_get($payment->metadata, 'liquidx.status', 'pending')
        ));

        $metadata = array_replace_recursive($payment->metadata ?? [], [
            'liquidx' => array_filter([
                'status' => $status,
                'status_date' => $statusPayload['date'] ?? data_get($statusPayload, 'data.response.date'),
                'status_details' => $statusPayload['details'] ?? null,
                'bank_tx_id' => $statusPayload['bankTxId'] ?? data_get($statusPayload, 'data.response.bankTxId'),
                'last_status_payload' => $statusPayload,
            ], fn ($value) => $value !== null),
        ], $extraMetadata);

        if (in_array($status, self::PAID_STATUSES, true)) {
            $payment->forceFill(['metadata' => $metadata])->save();
            $this->accessService->markPaymentAsPaid(
                $payment,
                $this->parseProviderTime($statusPayload['date'] ?? null)
            );

            return $payment;
        }

        if (in_array($status, self::FAILED_STATUSES, true)) {
            $payment->forceFill([
                'status' => $status,
                'metadata' => $metadata,
            ])->save();

            return $payment;
        }

        $payment->forceFill([
            'status' => 'pending',
            'metadata' => $metadata,
        ])->save();

        return $payment;
    }

    private function latestReusablePendingPayment(User $user): ?UserAccessPayment
    {
        $payment = $user->accessPayments()
            ->where('provider', self::PROVIDER)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if (! $payment || ! $this->pixPayloadFor($payment)) {
            return null;
        }

        return $payment;
    }

    private function integratedPaymentPayload(UserAccessPayment $payment, User $user, SubscriptionProfile $profile): array
    {
        return array_filter([
            'code' => $this->integratedCode(),
            'value' => number_format($payment->amount_cents / 100, 2, '.', ''),
            'order_id' => 'financialiq-access-'.$payment->id,
            'transaction_id' => $payment->provider_payment_id,
            'origin' => config('app.url'),
            'description' => Str::limit('FinancialIQ '.$profile->name, 43, ''),
            'identificacao_nome' => $this->payerName($user->name),
            'identificacao_email' => $user->email,
            'identificacao_telefone' => $this->payerPhone($user->phone),
            'thirdwallet' => config('financial.billing.liquidx.thirdwallet'),
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function client(): PendingRequest
    {
        $client = Http::external()
            ->baseUrl($this->baseUrl())
            ->asJson()
            ->acceptJson()
            ->timeout((int) config('financial.billing.liquidx.timeout', 20));

        $apiKey = $this->apiKey();
        if ($apiKey !== '') {
            $client = $client->withToken($apiKey);
        }

        return $client;
    }

    private function firstStatusPayload(array $providerPayload, UserAccessPayment $payment): array
    {
        $items = array_is_list($providerPayload)
            ? $providerPayload
            : data_get($providerPayload, 'data', []);

        if (! is_array($items)) {
            return [];
        }

        foreach ($items as $item) {
            if (is_array($item) && ($item['depix_id'] ?? null) === $payment->provider_payment_id) {
                return $item;
            }
        }

        $first = reset($items);

        return is_array($first) ? $first : [];
    }

    private function markProviderFailure(UserAccessPayment $payment, string $message): void
    {
        $payment->forceFill([
            'status' => 'failed',
            'metadata' => array_replace_recursive($payment->metadata ?? [], [
                'liquidx' => [
                    'status' => 'error',
                    'error_message' => $message,
                ],
            ]),
        ])->save();
    }

    private function providerError(Response $response): string
    {
        return (string) (
            $response->json('error')
            ?? $response->json('message')
            ?? $response->json('data')
            ?? $response->body()
            ?: 'Erro retornado pela Liquidx.'
        );
    }

    private function json(Response $response): array
    {
        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    private function parseProviderTime(?string $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function payerName(string $name): string
    {
        $ascii = Str::ascii($name);
        $clean = trim((string) preg_replace('/[^A-Za-z0-9 ]+/', ' ', $ascii));

        return $clean !== '' ? Str::limit($clean, 120, '') : 'Cliente FinancialIQ';
    }

    private function payerPhone(?string $phone): ?string
    {
        $phone ??= (string) config('financial.billing.liquidx.default_payer_phone', '');
        $digits = preg_replace('/\D+/', '', $phone);

        if (! $digits) {
            return null;
        }

        if (! str_starts_with($digits, '55')) {
            $digits = '55'.$digits;
        }

        return '+'.$digits;
    }

    private function normalizeProviderUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        return $this->baseUrlWithoutApiPath().'/'.ltrim($url, '/');
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('financial.billing.liquidx.base_url'), '/');
    }

    private function baseUrlWithoutApiPath(): string
    {
        return Str::replaceEnd('/api', '', $this->baseUrl());
    }

    private function apiKey(): string
    {
        return (string) config('financial.billing.liquidx.api_key');
    }

    private function integratedCode(): string
    {
        return (string) config('financial.billing.liquidx.integrated_code');
    }

    private function integratedPaymentPath(): string
    {
        return (string) config('financial.billing.liquidx.integrated_payment_path', '/integrated-payment');
    }

    private function integratedPaymentStatusPath(): string
    {
        return (string) config('financial.billing.liquidx.integrated_payment_status_path', '/integrated-payment/status');
    }
}
