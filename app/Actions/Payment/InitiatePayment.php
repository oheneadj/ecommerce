<?php

/**
 * Starts a payment attempt for an order with the currently active payment provider.
 */

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentApiLog;
use App\Payments\PaymentManager;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Idempotent per order: if a pending payment already exists for this order,
 * it's reused rather than duplicated (AGENTS.md §4a idempotency). Every
 * outbound call is logged to `payment_api_logs` regardless of outcome —
 * never gated on this Action's own success, since the log is a record of
 * what genuinely happened, not what we wish had happened (technical-design
 * §4g). Never calls Moolre/Paystack directly — only through PaymentGateway.
 */
class InitiatePayment
{
    use AsAction;

    public function __construct(
        private readonly PaymentManager $payments,
    ) {}

    /**
     * Anything between resolving the driver and getting a response back —
     * a missing/misconfigured API key (PaymentManager throws
     * InvalidArgumentException), a network error, a malformed provider
     * response — must never bubble up as an uncaught exception here. The
     * order has already been created by this point in checkout; a raw
     * 500 would strand the customer on a broken page with a real order
     * they can't pay for. Instead this always returns a Payment row (a
     * caught failure looks identical to a gateway-reported one — Failed
     * status, a customer-safe message in metadata.error), and logs the
     * underlying exception for whoever's on call to actually investigate.
     */
    public function handle(Order $order): Payment
    {
        $existing = $order->payments()->where('status', PaymentStatus::Pending)->latest('id')->first();

        if ($existing !== null) {
            return $existing;
        }

        $provider = $this->payments->getDefaultDriver();
        $requestPayload = ['order_id' => $order->id, 'order_number' => $order->order_number, 'amount' => $order->grand_total];

        try {
            $gateway = $this->payments->driver($provider);
            $result = $gateway->initiate($order);

            $responsePayload = $result->rawResponse;
            $statusCode = $result->success ? 200 : 422;
            $providerReference = $result->providerReference;
            $paymentStatus = $result->success ? PaymentStatus::Pending : PaymentStatus::Failed;
            $redirectUrl = $result->redirectUrl;
            $errorMessage = $result->errorMessage;
        } catch (Throwable $e) {
            Log::error('Payment initiation failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'provider' => $provider,
                'exception' => $e->getMessage(),
            ]);

            $responsePayload = ['error' => $e->getMessage()];
            $statusCode = 500;
            $providerReference = null;
            $paymentStatus = PaymentStatus::Failed;
            $redirectUrl = null;
            $errorMessage = 'Payment could not be started. Please try again or choose a different payment method.';
        }

        PaymentApiLog::query()->create([
            'order_id' => $order->id,
            'provider' => $provider,
            'action' => 'initiate',
            'request_payload' => $requestPayload,
            'response_payload' => $responsePayload,
            'status_code' => $statusCode,
        ]);

        return Payment::query()->create([
            'order_id' => $order->id,
            'provider' => $provider,
            'provider_reference' => $providerReference,
            'amount' => $order->grand_total,
            'currency' => 'GHS',
            'status' => $paymentStatus,
            'metadata' => [
                'redirect_url' => $redirectUrl,
                'error' => $errorMessage,
            ],
        ]);
    }
}
