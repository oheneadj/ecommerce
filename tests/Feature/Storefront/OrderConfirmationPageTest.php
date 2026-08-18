<?php

/**
 * Covers the customer-facing order confirmation page shown right after
 * checkout (/orders/{order}/confirmation).
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Enums\PaymentStatus;
use App\Jobs\VerifyPaymentWithGateway;
use App\Livewire\Storefront\OrderConfirmationPage;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Payments\PaymentManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Feature\Payment\FakePaymentGateway;
use Tests\TestCase;

class OrderConfirmationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_view_their_own_guest_order_confirmation(): void
    {
        $order = Order::factory()->create(['user_id' => null, 'guest_email' => 'guest@example.com']);

        $this->get("/orders/{$order->ulid}/confirmation")
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Thank you for your order');
    }

    public function test_a_guest_cannot_view_a_registered_customers_order_confirmation(): void
    {
        $order = Order::factory()->create();

        $this->get("/orders/{$order->ulid}/confirmation")->assertNotFound();
    }

    public function test_a_customer_sees_their_own_order_confirmation(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        $this->get("/orders/{$order->ulid}/confirmation")
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Thank you for your order');
    }

    public function test_a_customer_cannot_view_another_customers_order_confirmation(): void
    {
        $user = User::factory()->create();
        $otherOrder = Order::factory()->create();
        $this->actingAs($user);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(OrderConfirmationPage::class, ['orderUlid' => $otherOrder->ulid]);
    }

    /**
     * Paystack's callback_url convention appends `?reference=` after a
     * redirect-mode payment — used as the signal to check sooner than the
     * ~2-minute polling sweep, rather than making the customer wait.
     */
    public function test_landing_with_a_reference_and_a_still_pending_payment_dispatches_an_immediate_verification(): void
    {
        Queue::fake();
        $order = Order::factory()->create(['user_id' => null, 'guest_email' => 'guest@example.com']);
        $payment = Payment::factory()->create(['order_id' => $order->id, 'status' => PaymentStatus::Pending]);

        $this->get("/orders/{$order->ulid}/confirmation?reference=some-ref")->assertOk();

        Queue::assertPushed(VerifyPaymentWithGateway::class, fn (VerifyPaymentWithGateway $job) => $this->paymentIdOf($job) === $payment->id);
    }

    public function test_landing_without_a_reference_does_not_dispatch_verification(): void
    {
        Queue::fake();
        $order = Order::factory()->create(['user_id' => null, 'guest_email' => 'guest@example.com']);
        Payment::factory()->create(['order_id' => $order->id, 'status' => PaymentStatus::Pending]);

        $this->get("/orders/{$order->ulid}/confirmation")->assertOk();

        Queue::assertNotPushed(VerifyPaymentWithGateway::class);
    }

    /**
     * A reference is present but the payment already resolved (e.g. the
     * webhook beat the customer's own browser redirect back) — no point
     * dispatching a pointless repeat gateway call.
     */
    public function test_landing_with_a_reference_but_an_already_resolved_payment_does_not_dispatch_verification(): void
    {
        Queue::fake();
        $order = Order::factory()->create(['user_id' => null, 'guest_email' => 'guest@example.com']);
        Payment::factory()->create(['order_id' => $order->id, 'status' => PaymentStatus::Success]);

        $this->get("/orders/{$order->ulid}/confirmation?reference=some-ref")->assertOk();

        Queue::assertNotPushed(VerifyPaymentWithGateway::class);
    }

    public function test_a_pending_payment_shows_a_confirming_state_and_polls(): void
    {
        $order = Order::factory()->create(['user_id' => null, 'guest_email' => 'guest@example.com']);
        Payment::factory()->create(['order_id' => $order->id, 'status' => PaymentStatus::Pending]);

        $this->get("/orders/{$order->ulid}/confirmation")
            ->assertOk()
            ->assertSee('Confirming your payment')
            ->assertSee('wire:poll', escape: false);
    }

    /**
     * The core fix this page exists for: a synchronous failure at
     * initiation (or one resolved failed later via webhook/poll) is shown
     * honestly here — not the fixed "Thank you!" message every outcome
     * used to get regardless of what actually happened — with a retry
     * option, for a guest exactly the same as an authenticated customer.
     */
    public function test_a_failed_payment_shows_a_retry_button_for_a_guest(): void
    {
        $order = Order::factory()->create(['user_id' => null, 'guest_email' => 'guest@example.com']);
        Payment::factory()->create(['order_id' => $order->id, 'provider' => 'moolre', 'status' => PaymentStatus::Failed]);

        $this->get("/orders/{$order->ulid}/confirmation")
            ->assertOk()
            ->assertSee("Your payment didn't go through")
            ->assertSee('Retry payment');
    }

    public function test_a_failed_payment_shows_a_retry_button_for_an_authenticated_customer(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        Payment::factory()->create(['order_id' => $order->id, 'provider' => 'moolre', 'status' => PaymentStatus::Failed]);
        $this->actingAs($user);

        $this->get("/orders/{$order->ulid}/confirmation")->assertSee('Retry payment');
    }

    public function test_a_guest_can_retry_a_failed_payment(): void
    {
        FakePaymentGateway::reset();
        FakePaymentGateway::$initiateSucceeds = true;
        $this->app->make(PaymentManager::class)->extend('moolre', fn () => new FakePaymentGateway);
        DB::table('payment_provider_settings')->insert([
            'provider' => 'moolre',
            'enabled' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $order = Order::factory()->create(['user_id' => null, 'guest_email' => 'guest@example.com']);
        Payment::factory()->create(['order_id' => $order->id, 'provider' => 'moolre', 'status' => PaymentStatus::Failed]);

        Livewire::test(OrderConfirmationPage::class, ['orderUlid' => $order->ulid])
            ->call('retryPayment')
            ->assertHasNoErrors();

        $this->assertTrue($order->payments()->where('status', PaymentStatus::Pending)->exists());
    }

    private function paymentIdOf(VerifyPaymentWithGateway $job): int
    {
        $reflection = new \ReflectionProperty($job, 'paymentId');

        return $reflection->getValue($job);
    }
}
