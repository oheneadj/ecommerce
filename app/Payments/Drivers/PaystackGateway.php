<?php

/**
 * Paystack's payment API driver (card payments).
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
 * Uses Paystack's hosted checkout (transaction/initialize returns an
 * `authorization_url` the customer is redirected to) — card details are
 * entered on Paystack's own page and never touch this platform, keeping
 * card data entirely out of our PCI scope.
 */
readonly class PaystackGateway implements PaymentGateway
{
    private const BASE_URL = 'https://api.paystack.co';

    public function __construct(
        private string $secretKey,
    ) {}

    public function initiate(Order $order, string $channel): PaymentInitiationResult
    {
        $response = $this->client()->post('/transaction/initialize', [
            'email' => optional($order->user)->email ?? $order->guest_email ?? 'no-reply@example.com',
            // Paystack's smallest currency unit for GHS is pesewas — no conversion needed.
            'amount' => $order->grand_total,
            'currency' => 'GHS',
            'reference' => $order->order_number.'-'.now()->timestamp,
            'metadata' => ['order_id' => $order->id, 'order_number' => $order->order_number],
        ]);

        if ($response->failed() || $response->json('status') !== true) {
            return new PaymentInitiationResult(
                success: false,
                errorMessage: $response->json('message') ?? 'Paystack transaction initialization failed.',
                rawResponse: $response->json() ?? [],
            );
        }

        return new PaymentInitiationResult(
            success: true,
            providerReference: $response->json('data.reference'),
            redirectUrl: $response->json('data.authorization_url'),
            rawResponse: $response->json() ?? [],
        );
    }

    public function verify(string $providerReference): PaymentVerificationResult
    {
        $response = $this->client()->get("/transaction/verify/{$providerReference}");

        $status = match ($response->json('data.status')) {
            'success' => PaymentStatus::Success,
            'failed', 'abandoned' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };

        return new PaymentVerificationResult(
            status: $status,
            providerReference: $response->json('data.reference'),
            rawResponse: $response->json() ?? [],
        );
    }

    public function refund(Payment $payment, int $amount, ?string $reason = null): RefundResult
    {
        $response = $this->client()->post('/refund', [
            'transaction' => $payment->provider_reference,
            'amount' => $amount,
            'merchant_note' => $reason,
        ]);

        if ($response->failed() || $response->json('status') !== true) {
            return new RefundResult(
                success: false,
                errorMessage: $response->json('message') ?? 'Paystack refund request failed.',
                rawResponse: $response->json() ?? [],
            );
        }

        return new RefundResult(
            success: true,
            providerRefundReference: (string) $response->json('data.id'),
            rawResponse: $response->json() ?? [],
        );
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->header('x-paystack-signature');

        if (! is_string($signature) || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha512', $request->getContent(), $this->secretKey);

        return hash_equals($expected, $signature);
    }

    public function webhookEventId(Request $request): string
    {
        return $request->input('event').':'.$request->input('data.reference');
    }

    public function paymentReferenceFromWebhook(Request $request): ?string
    {
        return $request->input('data.reference');
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)->withToken($this->secretKey)->timeout(15);
    }
}
