<?php

/**
 * Covers that a transport-level gateway failure (timeout, connection
 * refused — as opposed to a normal gateway-reported error response) still
 * produces a PaymentApiLog row, for both VerifyPaymentWithGateway and
 * IssueProviderRefund. Previously the log write only happened on the
 * gateway responding normally; an exception thrown before that skipped it
 * entirely, leaving no reconciliation record for exactly the failure mode
 * that most needs one.
 */

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Jobs\IssueProviderRefund;
use App\Jobs\VerifyPaymentWithGateway;
use App\Models\Payment;
use App\Models\PaymentApiLog;
use App\Models\Refund;
use App\Payments\PaymentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PaymentApiLoggingOnTransportFailureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FakePaymentGateway::reset();
        $this->app->make(PaymentManager::class)->extend('fake', fn () => new FakePaymentGateway);
    }

    public function test_a_connection_failure_during_verification_still_writes_an_api_log_entry(): void
    {
        FakePaymentGateway::$verifyThrows = true;
        $payment = Payment::factory()->create(['provider' => 'fake', 'provider_reference' => 'ref-1', 'status' => PaymentStatus::Pending]);

        try {
            (new VerifyPaymentWithGateway($payment->id))->handle($this->app->make(PaymentManager::class));
        } catch (RuntimeException) {
            // The job re-throws so its own retry policy applies — expected.
        }

        $log = PaymentApiLog::query()->where('payment_id', $payment->id)->sole();

        $this->assertSame('fake', $log->provider);
        $this->assertSame('verify', $log->action);
        $this->assertSame(500, $log->status_code);
        $this->assertStringContainsString('Simulated connection failure', $log->response_payload['error']);
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_a_connection_failure_during_refund_still_writes_an_api_log_entry(): void
    {
        FakePaymentGateway::$refundThrows = true;
        $payment = Payment::factory()->create(['provider' => 'fake', 'amount' => 1000, 'status' => PaymentStatus::Success]);
        $refund = Refund::factory()->create(['payment_id' => $payment->id, 'amount' => 400, 'status' => RefundStatus::Pending]);

        try {
            (new IssueProviderRefund($refund->id))->handle($this->app->make(PaymentManager::class));
        } catch (RuntimeException) {
            // The job re-throws so its own retry policy applies — expected.
        }

        $log = PaymentApiLog::query()->where('payment_id', $payment->id)->sole();

        $this->assertSame('fake', $log->provider);
        $this->assertSame('refund', $log->action);
        $this->assertSame(500, $log->status_code);
        $this->assertStringContainsString('Simulated connection failure', $log->response_payload['error']);
        $this->assertSame(RefundStatus::Pending, $refund->fresh()->status);
    }
}
