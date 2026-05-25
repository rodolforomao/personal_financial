<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionProfile;
use App\Models\UserAccessPayment;
use App\Services\LiquidxPaymentService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SubscriptionPaymentController extends Controller
{
    public function store(Request $request, LiquidxPaymentService $payments): RedirectResponse
    {
        $user = $request->user()->loadMissing('subscriptionProfile');

        if ($user->hasActivePlatformAccess()) {
            return redirect()->route('dashboard');
        }

        try {
            $payments->startPixPayment($user, $user->subscriptionProfile ?? $this->defaultSubscriptionProfile());
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'payment' => 'Não foi possível gerar o QR code agora. Verifique a configuração da Liquidx e tente novamente.',
            ]);
        }

        return redirect()
            ->route('subscription.pending')
            ->with('success', 'QR code PIX gerado. Pague pelo app do banco e depois confira o status.');
    }

    public function check(Request $request, LiquidxPaymentService $payments): RedirectResponse
    {
        $user = $request->user();
        $payment = $payments->latestPaymentFor($user);

        if (! $payment) {
            return back()->withErrors(['payment' => 'Nenhum pagamento PIX foi gerado para sua conta ainda.']);
        }

        try {
            $payments->refreshPaymentStatus($payment);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'payment' => 'Não foi possível consultar o status do pagamento agora. Tente novamente em instantes.',
            ]);
        }

        $user->refresh();
        if ($user->hasActivePlatformAccess()) {
            return redirect()
                ->route('dashboard')
                ->with('success', 'Pagamento confirmado. Seu acesso foi liberado.');
        }

        return redirect()
            ->route('subscription.pending')
            ->with('warning', 'Pagamento ainda não confirmado pela Liquidx. Aguarde alguns segundos e verifique novamente.');
    }

    public function qrCode(Request $request, UserAccessPayment $payment, LiquidxPaymentService $payments): Response
    {
        abort_unless($payment->user_id === $request->user()->id, 403);

        $payload = $payments->pixPayloadFor($payment);
        abort_if($payload === null, 404);

        $renderer = new ImageRenderer(new RendererStyle(280, 2), new SvgImageBackEnd);
        $writer = new Writer($renderer);

        return response($writer->writeString($payload), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function defaultSubscriptionProfile(): SubscriptionProfile
    {
        return SubscriptionProfile::query()->firstOrCreate(
            ['slug' => 'mensal'],
            [
                'name' => 'Mensal',
                'monthly_price_cents' => 2000,
                'description' => 'Acesso padrão ao Financial IQ.',
                'is_active' => true,
            ]
        );
    }
}
