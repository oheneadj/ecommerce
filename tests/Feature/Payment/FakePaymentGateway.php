<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\PaymentInitiationResult;
use App\Payments\PaymentVerificationResult;
use App\Payments\RefundResult;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A test double proving Actions never depend on a specific vendor —
 * registered via PaymentManager::extend(), the same public extension point
 * a real new provider would use.
 */
class FakePaymentGateway implements PaymentGateway
{
    public static bool $initiateSucceeds = true;

    public static PaymentStatus $verifyStatus = PaymentStatus::Success;

    public static bool $refundSucceeds = true;

    public static bool $webhookSignatureValid = true;

    public static int $providerReferenceCounter = 0;

    /**
     * Simulates a transport-level failure (timeout, connection refused) —
     * distinct from $verifyStatus/$refundSucceeds, which simulate the
     * gateway responding normally with a failure result.
     */
    public static bool $verifyThrows = false;

    public static bool $refundThrows = false;

    public static function reset(): void
    {
        self::$initiateSucceeds = true;
        self::$verifyStatus = PaymentStatus::Success;
        self::$refundSucceeds = true;
        self::$webhookSignatureValid = true;
        self::$providerReferenceCounter = 0;
        self::$verifyThrows = false;
        self::$refundThrows = false;
    }

    public function initiate(Order $order, string $channel): PaymentInitiationResult
    {
        if (! self::$initiateSucceeds) {
            return new PaymentInitiationResult(success: false, errorMessage: 'Simulated failure.');
        }

        self::$providerReferenceCounter++;

        return new PaymentInitiationResult(
            success: true,
            providerReference: 'fake-ref-'.self::$providerReferenceCounter,
            redirectUrl: 'https://fake-gateway.test/pay/fake-ref-'.self::$providerReferenceCounter,
        );
    }

    public function verify(string $providerReference): PaymentVerificationResult
    {
        if (self::$verifyThrows) {
            throw new RuntimeException('Simulated connection failure.');
        }

        return new PaymentVerificationResult(
            status: self::$verifyStatus,
            providerReference: $providerReference,
        );
    }

    public function refund(Payment $payment, int $amount, ?string $reason = null): RefundResult
    {
        if (self::$refundThrows) {
            throw new RuntimeException('Simulated connection failure.');
        }

        if (! self::$refundSucceeds) {
            return new RefundResult(success: false, errorMessage: 'Simulated refund failure.');
        }

        return new RefundResult(success: true, providerRefundReference: 'fake-refund-ref');
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        return self::$webhookSignatureValid;
    }

    public function webhookEventId(Request $request): string
    {
        return (string) $request->input('event_id', 'fake-event-id');
    }

    public function paymentReferenceFromWebhook(Request $request): ?string
    {
        return $request->input('provider_reference');
    }
}
