<?php

/**
 * Covers that the public, unauthenticated payment webhook endpoint is
 * rate-limited — previously it had none, letting an attacker flood it
 * with unlimited requests, each writing a WebhookEvent row.
 */

declare(strict_types=1);

namespace Tests\Feature\Payment;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_webhook_endpoint_rejects_requests_past_the_rate_limit(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->postJson('/webhooks/payments/fake', ['event_id' => "evt-{$i}"]);
        }

        $response = $this->postJson('/webhooks/payments/fake', ['event_id' => 'evt-over-limit']);

        $response->assertStatus(429);
    }
}
