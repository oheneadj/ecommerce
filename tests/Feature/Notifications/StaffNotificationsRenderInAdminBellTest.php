<?php

/**
 * Covers a gap where staff-facing database notifications never populated
 * Filament's own expected payload shape, so they silently rendered with a
 * blank title in the admin notification bell.
 */

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\BackupFailed;
use App\Notifications\BackupSucceeded;
use App\Notifications\CriticalHealthAlert;
use App\Notifications\LowStockAlert;
use App\Notifications\ReservationsAtRiskAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffNotificationsRenderInAdminBellTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Filament's admin bell (`->databaseNotifications()`) reconstructs a
     * `Filament\Notifications\Notification` from the raw database payload
     * via `Notification::fromArray()`, which reads a `title` key — a
     * notification whose `toDatabase()` never set one rendered with a
     * blank title, effectively invisible in the dropdown even though the
     * row existed.
     */
    public function test_backup_failed_notification_data_includes_a_filament_title(): void
    {
        $data = (new BackupFailed('Some\\Exception\\Class'))->toDatabase(User::factory()->make());

        $this->assertArrayHasKey('title', $data);
        $this->assertNotEmpty($data['title']);
    }

    public function test_backup_succeeded_notification_data_includes_a_filament_title(): void
    {
        $data = (new BackupSucceeded(1024))->toDatabase(User::factory()->make());

        $this->assertArrayHasKey('title', $data);
        $this->assertNotEmpty($data['title']);
    }

    public function test_critical_health_alert_notification_data_includes_a_filament_title(): void
    {
        $data = (new CriticalHealthAlert(['Some check']))->toDatabase(User::factory()->make());

        $this->assertArrayHasKey('title', $data);
        $this->assertNotEmpty($data['title']);
    }

    public function test_low_stock_alert_notification_data_includes_a_filament_title(): void
    {
        $variant = ProductVariant::factory()->create();

        $data = (new LowStockAlert($variant))->toDatabase(User::factory()->make());

        $this->assertArrayHasKey('title', $data);
        $this->assertNotEmpty($data['title']);
    }

    public function test_reservations_at_risk_alert_notification_data_includes_a_filament_title(): void
    {
        $variant = ProductVariant::factory()->create();

        $data = (new ReservationsAtRiskAlert($variant, [1, 2]))->toDatabase(User::factory()->make());

        $this->assertArrayHasKey('title', $data);
        $this->assertNotEmpty($data['title']);
    }
}
