<?php

/**
 * Covers MonthlyRevenueChart's "last 6 months" fallback (no date-range filter applied).
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Filament\Widgets\MonthlyRevenueChart;
use App\Models\Payment;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MonthlyRevenueChartTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    /**
     * Regression: the "last 6 months" fallback anchored its whole 6-month
     * window on the server's raw UTC `now()`, even though every other
     * dashboard date calculation (DashboardMetricsQuery) was fixed to use
     * the store's configured timezone. A payment still "today" in the
     * store's own timezone, but already tomorrow in UTC, would have been
     * silently excluded from the chart's current month.
     */
    public function test_the_last_6_months_fallback_uses_the_stores_configured_timezone(): void
    {
        StoreSetting::current()->update(['timezone' => 'America/New_York']);
        $this->actingAs($this->admin());

        // 2026-08-21 02:00:00 UTC is 2026-08-20 22:00:00 in
        // America/New_York — still August from the store's own
        // perspective, even though the UTC calendar date has already
        // rolled to the 21st.
        $this->travelTo(Carbon::parse('2026-08-21 02:00:00', 'UTC'));

        Payment::factory()->create([
            'status' => PaymentStatus::Success,
            'amount' => 1500,
            'created_at' => Carbon::parse('2026-08-20 23:30:00', 'UTC'),
        ]);

        $widget = Livewire::test(MonthlyRevenueChart::class, ['pageFilters' => []])->instance();

        $getData = new \ReflectionMethod($widget, 'getData');
        $data = $getData->invoke($widget);

        // The current-month label must read "Aug 2026", and its revenue
        // must include the payment above — a UTC-anchored fallback would
        // have labeled the current month "Sep 2026" and excluded it.
        $this->assertSame('Aug 2026', end($data['labels']));
        $this->assertEqualsWithDelta(15.0, end($data['datasets'][0]['data']), 0.001);
    }
}
