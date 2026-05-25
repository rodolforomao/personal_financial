<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LiquidxPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiquidxPaymentWebhookController extends Controller
{
    public function __invoke(Request $request, LiquidxPaymentService $payments): JsonResponse
    {
        $secret = (string) config('financial.billing.liquidx.webhook_secret', '');
        if ($secret !== '') {
            $provided = (string) ($request->query('secret') ?: $request->header('X-Liquidx-Webhook-Secret', ''));
            abort_unless(hash_equals($secret, $provided), 403);
        }

        $payment = $payments->handleWebhook($request->all());

        return response()->json([
            'ok' => true,
            'payment_id' => $payment?->id,
        ]);
    }
}
