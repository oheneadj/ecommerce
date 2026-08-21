<?php

/**
 * Covers StoreSetting::current() — the single settings row every part of
 * the app reads through, and the singleton_key constraint that stops two
 * concurrent first-touch requests from ever producing two competing rows.
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\StoreSetting;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_creates_the_row_on_first_call(): void
    {
        $this->assertDatabaseCount('store_settings', 0);

        StoreSetting::current();

        $this->assertDatabaseCount('store_settings', 1);
    }

    public function test_current_returns_the_same_row_on_repeat_calls(): void
    {
        $first = StoreSetting::current();
        $second = StoreSetting::current();

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('store_settings', 1);
    }

    public function test_current_finds_an_already_existing_row_rather_than_duplicating_it(): void
    {
        $winner = StoreSetting::query()->create(['singleton_key' => 'singleton']);

        $result = StoreSetting::current();

        $this->assertSame($winner->id, $result->id);
        $this->assertDatabaseCount('store_settings', 1);
    }

    /**
     * The actual mechanism closing the first-touch race: `current()`'s
     * `firstOrCreate` is a SELECT then an INSERT, not atomic, so two
     * concurrent first-touch requests could both see no row and both try
     * to insert one — true concurrency can't be exercised in-process
     * against SQLite here (same limitation documented elsewhere in this
     * suite, e.g. InventoryManagementTest's own concurrency test), so
     * this instead proves the invariant the constraint exists to
     * guarantee: a second row can never actually land in the table.
     */
    public function test_the_singleton_key_column_rejects_a_second_row(): void
    {
        StoreSetting::query()->create(['singleton_key' => 'singleton']);

        $this->expectException(QueryException::class);

        StoreSetting::query()->create(['singleton_key' => 'singleton']);
    }

    /**
     * A calendar date in a UTC-behind store (e.g. America/New_York,
     * UTC-4 in summer) starts later than midnight UTC — "2026-08-21"
     * there doesn't start until "2026-08-21 04:00:00" UTC.
     */
    public function test_start_of_day_utc_converts_a_date_from_the_store_timezone(): void
    {
        $store = StoreSetting::current();
        $store->update(['timezone' => 'America/New_York']);

        $result = $store->startOfDayUtc('2026-08-21');

        $this->assertSame('2026-08-21 04:00:00', $result->toDateTimeString());
    }

    public function test_end_of_day_utc_converts_a_date_from_the_store_timezone(): void
    {
        $store = StoreSetting::current();
        $store->update(['timezone' => 'America/New_York']);

        $result = $store->endOfDayUtc('2026-08-21');

        $this->assertSame('2026-08-22 03:59:59', $result->toDateTimeString());
    }

    public function test_day_boundaries_are_unaffected_for_a_utc_store(): void
    {
        $store = StoreSetting::current();
        $store->update(['timezone' => 'UTC']);

        $this->assertSame('2026-08-21 00:00:00', $store->startOfDayUtc('2026-08-21')->toDateTimeString());
        $this->assertSame('2026-08-21 23:59:59', $store->endOfDayUtc('2026-08-21')->toDateTimeString());
    }
}
