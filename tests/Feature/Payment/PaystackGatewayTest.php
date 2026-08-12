<?php

/**
 * Covers PaystackGateway::initiate() — the request shape sent to Paystack
 * (specifically the explicit callback_url, added so redirect-mode checkout
 * works with zero required Paystack Dashboard configuration) and that the
 * access_code needed for popup checkout is returned.
 */

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Models\Order;
use App\Payments\Drivers\PaystackGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaystackGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_initiate_sends_an_explicit_callback_url_pointing_at_the_order_confirmation_page(): void
    {
        $order = Order::factory()->create();

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['reference' => 'ref-1', 'authorization_url' => 'https://checkout.paystack.com/ref-1', 'access_code' => 'access-code-1'],
            ]),
        ]);

        (new PaystackGateway(secretKey: 'test-secret'))->initiate($order);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.paystack.co/transaction/initialize'
            && $request['callback_url'] === route('orders.confirmation', ['order' => $order]));
    }

    public function test_initiate_returns_the_access_code_for_popup_checkout(): void
    {
        $order = Order::factory()->create();

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['reference' => 'ref-1', 'authorization_url' => 'https://checkout.paystack.com/ref-1', 'access_code' => 'access-code-1'],
            ]),
        ]);

        $result = (new PaystackGateway(secretKey: 'test-secret'))->initiate($order);

        $this->assertTrue($result->success);
        $this->assertSame('access-code-1', $result->accessCode);
        $this->assertSame('https://checkout.paystack.com/ref-1', $result->redirectUrl);
    }

    /**
     * PaystackGateway itself doesn't catch connection failures (matches
     * MoolreGateway — neither payment driver does) — App\Actions\Payment\
     * InitiatePayment is the layer responsible for normalizing that into a
     * Failed payment, since it's the one place that must never let an
     * uncaught exception strand a customer with an order they can't pay
     * for. This just documents that the exception really does propagate
     * up to that boundary rather than getting silently swallowed here.
     */
    public function test_a_connection_failure_during_initiate_propagates_to_the_caller(): void
    {
        $order = Order::factory()->create();

        Http::fake(function (): void {
            throw new ConnectionException('Connection timed out.');
        });

        $this->expectException(ConnectionException::class);

        (new PaystackGateway(secretKey: 'test-secret'))->initiate($order);
    }
}
