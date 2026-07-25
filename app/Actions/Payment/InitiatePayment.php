<?php

/**
 * Starts a payment attempt for an order over a chosen channel.
 */

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentApiLog;
use App\Payments\PaymentManager;
use Lorisleiva\Actions\Concerns\AsAction;

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

    public function handle(Order $order, string $channel): Payment
    {
        $existing = $order->payments()->where('status', PaymentStatus::Pending)->latest('id')->first();

        if ($existing !== null) {
            return $existing;
        }

        $gateway = $this->payments->driverForChannel($channel);
        $provider = (string) config("payments.channels.{$channel}");

        $requestPayload = ['order_id' => $order->id, 'order_number' => $order->order_number, 'amount' => $order->grand_total, 'channel' => $channel];
        $result = $gateway->initiate($order, $channel);

        PaymentApiLog::query()->create([
            'order_id' => $order->id,
            'provider' => $provider,
            'action' => 'initiate',
            'request_payload' => $requestPayload,
            'response_payload' => $result->rawResponse,
            'status_code' => $result->success ? 200 : 422,
        ]);

        return Payment::query()->create([
            'order_id' => $order->id,
            'provider' => $provider,
            'provider_reference' => $result->providerReference,
            'channel' => $channel,
            'amount' => $order->grand_total,
            'currency' => 'GHS',
            'status' => $result->success ? PaymentStatus::Pending : PaymentStatus::Failed,
            'metadata' => [
                'redirect_url' => $result->redirectUrl,
                'error' => $result->errorMessage,
            ],
        ]);
    }
}
