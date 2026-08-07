<?php

/**
 * Covers Address's ULID identifier — previously missing, unlike every
 * other externally-referenceable model in this codebase.
 */

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_ulid_is_generated_on_creation(): void
    {
        $address = Address::factory()->create();

        $this->assertNotEmpty($address->ulid);
    }

    public function test_the_ulid_column_is_used_for_route_model_binding(): void
    {
        $address = Address::factory()->create();

        $this->assertSame('ulid', $address->getRouteKeyName());
        $this->assertSame($address->ulid, $address->getRouteKey());
    }
}
