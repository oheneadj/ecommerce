<?php

/**
 * Covers the delete guard on Coupon — coupon_usages.coupon_id is
 * restrictOnDelete() at the DB level, so deleting an already-used coupon
 * must be blocked with a clean message rather than a raw QueryException.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Resources\Coupons\Pages\EditCoupon;
use App\Filament\Resources\Coupons\Pages\ListCoupons;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CouponDeleteGuardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_deleting_an_unused_coupon_succeeds(): void
    {
        $this->actingAs($this->admin());

        $coupon = Coupon::factory()->create();

        Livewire::test(EditCoupon::class, ['record' => $coupon->getRouteKey()])
            ->callAction('delete');

        $this->assertModelMissing($coupon);
    }

    public function test_deleting_an_already_used_coupon_is_blocked(): void
    {
        $this->actingAs($this->admin());

        $coupon = Coupon::factory()->create();
        CouponUsage::factory()->create(['coupon_id' => $coupon->id]);

        Livewire::test(EditCoupon::class, ['record' => $coupon->getRouteKey()])
            ->callAction('delete')
            ->assertNotified('Cannot delete coupon');

        $this->assertModelExists($coupon);
    }

    public function test_bulk_deleting_coupons_is_blocked_while_any_selected_one_is_in_use(): void
    {
        $this->actingAs($this->admin());

        $unused = Coupon::factory()->create();
        $inUse = Coupon::factory()->create();
        CouponUsage::factory()->create(['coupon_id' => $inUse->id]);

        Livewire::test(ListCoupons::class)
            ->callTableBulkAction('delete', [$unused, $inUse])
            ->assertNotified('Cannot delete coupons');

        $this->assertModelExists($unused);
        $this->assertModelExists($inUse);
    }
}
