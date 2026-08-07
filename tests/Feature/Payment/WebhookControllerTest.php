<?php

/**
 * Covers the webhook controller's documented invariant: it must always
 * respond 200, even when something inside webhook handling throws —
 * otherwise the provider retries a request we've already recorded (or
 * one that was never valid to begin with, like a probe against an
 * unrecognized provider name).
 */

declare(strict_types=1);

namespace Tests\Feature\Payment;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unrecognized_provider_still_receives_a_200_response(): void
    {
        $response = $this->postJson('/webhooks/payments/not-a-real-provider', ['event_id' => 'evt-1']);

        $response->assertStatus(200);
    }
}
