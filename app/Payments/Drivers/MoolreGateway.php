<?php

/**
 * Moolre's payment API driver (mobile money).
 */

declare(strict_types=1);

namespace App\Payments\Drivers;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\PaymentInitiationResult;
use App\Payments\PaymentVerificationResult;
use App\Payments\RefundResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Mirrors App\Sms\Drivers\MoolreSms's use of Moolre's "open" API namespace.
 * Mobile money payment is a request-to-pay flow: the customer approves a
 * prompt on their phone rather than being redirected, so `redirectUrl` is
 * always null here.
 */
readonly class MoolreGateway implements PaymentGateway
{
    private const BASE_URL = 'https://api.moolre.com/open';

    public function __construct(
        private string $apiKey,
        private string $webhookSecret,
    ) {}

    public function initiate(Order $order): PaymentInitiationResult
    {
        $response = $this->client()->post('/payment/request', [
            'reference' => $order->order_number.'-'.now()->timestamp,
            'amount' => $order->grand_total,
            'currency' => 'GHS',
            'recipient' => $order->guest_phone ?? optional($order->user)->phone,
        ]);

        if ($response->failed() || ! ($response->json('success') ?? false)) {
            return new PaymentInitiationResult(
                success: false,
                errorMessage: $response->json('message') ?? 'Moolre payment request failed.',
                rawResponse: $response->json() ?? [],
            );
        }

        return new PaymentInitiationResult(
            success: true,
            providerReference: $response->json('data.id'),
            rawResponse: $response->json() ?? [],
        );
    }

    public function verify(string $providerReference): PaymentVerificationResult
    {
        $response = $this->client()->get("/payment/status/{$providerReference}");

        $status = match ($response->json('data.status')) {
            'success', 'completed' => PaymentStatus::Success,
            'failed', 'cancelled' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };

        return new PaymentVerificationResult(
            status: $status,
            providerReference: $response->json('data.id'),
            rawResponse: $response->json() ?? [],
        );
    }

    public function refund(Payment $payment, int $amount, ?string $reason = null): RefundResult
    {
        $response = $this->client()->post('/payment/refund', [
            'reference' => $payment->provider_reference,
            'amount' => $amount,
            'reason' => $reason,
        ]);

        if ($response->failed() || ! ($response->json('success') ?? false)) {
            return new RefundResult(
                success: false,
                errorMessage: $response->json('message') ?? 'Moolre refund request failed.',
                rawResponse: $response->json() ?? [],
            );
        }

        return new RefundResult(
            success: true,
            providerRefundReference: $response->json('data.id'),
            rawResponse: $response->json() ?? [],
        );
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->header('x-moolre-signature');

        if (! \is_string($signature) || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $this->webhookSecret);

        return hash_equals($expected, $signature);
    }

    public function webhookEventId(Request $request): string
    {
        return (string) $request->input('data.id', $request->input('id', ''));
    }

    public function paymentReferenceFromWebhook(Request $request): ?string
    {
        return $request->input('data.id');
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)->withToken($this->apiKey)->timeout(15);
    }
}
