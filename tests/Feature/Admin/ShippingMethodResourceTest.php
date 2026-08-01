<?php

/**
 * Covers ShippingMethod's route-model-binding key — it previously had no
 * ULID/slug and exposed its raw bigint id in admin URLs, unlike every
 * other comparable resource.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\ShippingMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingMethodResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipping_method_does_not_expose_a_raw_bigint_id_via_route_key(): void
    {
        $shippingMethod = ShippingMethod::factory()->create();

        $this->assertSame('ulid', $shippingMethod->getRouteKeyName());
        $this->assertNotSame((string) $shippingMethod->id, $shippingMethod->getRouteKey());
    }
}
