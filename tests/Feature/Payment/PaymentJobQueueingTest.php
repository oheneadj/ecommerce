<?php

/**
 * Covers that payment gateway calls are actually dispatched to queued
 * jobs (not called inline), routed to the `external-api` queue, and
 * declare proper retry/failure hygiene — per this project's "external API
 * calls must be queued" convention.
 */

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Actions\Payment\HandlePaymentWebhook;
use App\Actions\Payment\ProcessRefund;
use App\Actions\Payment\SettlePaymentSuccess;
use App\Actions\Payment\VerifyPendingPayments;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Jobs\GenerateOrderInvoicePdf;
use App\Jobs\IssueProviderRefund;
use App\Jobs\VerifyPaymentWithGateway;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Payments\PaymentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaymentJobQueueingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FakePaymentGateway::reset();
        $this->app->make(PaymentManager::class)->extend('fake', fn () => new FakePaymentGateway);
        config([
            'payments.channels.mobile_money' => 'fake',
            'payments.channels.card' => 'fake',
        ]);
    }

    public function test_a_matched_webhook_dispatches_verification_instead_of_calling_the_gateway_inline(): void
    {
        Queue::fake();

        $payment = Payment::factory()->create(['provider' => 'fake', 'provider_reference' => 'fake-ref-1', 'status' => PaymentStatus::Pending]);
        $request = Request::create('/webhooks/payments/fake', 'POST', ['provider_reference' => 'fake-ref-1', 'event_id' => 'evt-1']);

        HandlePaymentWebhook::run($request, 'fake');

        Queue::assertPushed(VerifyPaymentWithGateway::class);
        // Receiving and queuing the event is itself the idempotent action —
        // processed_at is set right away, independent of the queued job's
        // eventual outcome.
        $this->assertNotNull(WebhookEvent::query()->where('event_id', 'evt-1')->first()?->processed_at);
        // Since verification is queued, the payment itself hasn't moved yet.
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_verify_pending_payments_dispatches_one_job_per_pending_payment(): void
    {
        Queue::fake();

        Payment::factory()->create(['provider' => 'fake', 'provider_reference' => 'ref-1', 'status' => PaymentStatus::Pending, 'created_at' => now()->subMinutes(5)]);
        Payment::factory()->create(['provider' => 'fake', 'provider_reference' => 'ref-2', 'status' => PaymentStatus::Pending, 'created_at' => now()->subMinutes(5)]);
        // Too recent — still within the grace period, shouldn't be dispatched.
        Payment::factory()->create(['provider' => 'fake', 'provider_reference' => 'ref-3', 'status' => PaymentStatus::Pending, 'created_at' => now()]);

        $count = VerifyPendingPayments::run();

        $this->assertSame(2, $count);
        Queue::assertPushed(VerifyPaymentWithGateway::class, 2);
    }

    public function test_processing_a_refund_dispatches_the_gateway_call_instead_of_calling_it_inline(): void
    {
        Queue::fake();

        $payment = Payment::factory()->create(['provider' => 'fake', 'amount' => 1000, 'status' => PaymentStatus::Success]);

        $refund = ProcessRefund::run($payment, 400);

        Queue::assertPushed(IssueProviderRefund::class);
        $this->assertSame(RefundStatus::Pending, $refund->status);
    }

    public function test_verify_payment_job_declares_retry_and_timeout_hygiene(): void
    {
        $job = new VerifyPaymentWithGateway(1);

        $this->assertSame(3, $job->tries);
        $this->assertSame(30, $job->timeout);
        $this->assertSame([10, 30, 60], $job->backoff);
        $this->assertSame('external-api', $job->queue);
    }

    public function test_issue_provider_refund_job_declares_retry_and_timeout_hygiene(): void
    {
        $job = new IssueProviderRefund(1);

        $this->assertSame(3, $job->tries);
        $this->assertSame(30, $job->timeout);
        $this->assertSame([10, 30, 60], $job->backoff);
        $this->assertSame('external-api', $job->queue);
    }

    public function test_settling_a_successful_payment_dispatches_invoice_generation_instead_of_generating_it_inline(): void
    {
        Queue::fake();

        $order = Order::factory()->create(['status' => OrderStatus::Pending]);
        $payment = Payment::factory()->create(['order_id' => $order->id, 'provider' => 'fake', 'status' => PaymentStatus::Pending]);

        SettlePaymentSuccess::run($payment);

        Queue::assertPushed(GenerateOrderInvoicePdf::class);
    }

    public function test_generate_order_invoice_pdf_job_declares_retry_and_timeout_hygiene(): void
    {
        $job = new GenerateOrderInvoicePdf(1);

        $this->assertSame(3, $job->tries);
        $this->assertSame(60, $job->timeout);
        $this->assertSame([10, 30, 60], $job->backoff);
        $this->assertSame('processing', $job->queue);
    }
}
