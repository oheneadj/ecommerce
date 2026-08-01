<?php

/**
 * Covers that Coupon's `value` field is required for Fixed/Percentage
 * types — previously it could be left blank, so a saved coupon would
 * silently apply a $0/0% discount at checkout with no error anywhere.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\CouponType;
use App\Enums\UserRole;
use App\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CouponFormValidationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_a_fixed_coupon_requires_a_value(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateCoupon::class)
            ->fillForm(['code' => 'NOVALUE', 'type' => CouponType::Fixed->value, 'value' => null])
            ->call('create')
            ->assertHasFormErrors(['value' => 'required']);
    }

    public function test_a_percentage_coupon_requires_a_value(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateCoupon::class)
            ->fillForm(['code' => 'NOVALUE2', 'type' => CouponType::Percentage->value, 'value' => null])
            ->call('create')
            ->assertHasFormErrors(['value' => 'required']);
    }

    public function test_a_free_shipping_coupon_does_not_require_a_value(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateCoupon::class)
            ->fillForm(['code' => 'FREESHIP', 'type' => CouponType::FreeShipping->value, 'value' => null])
            ->call('create')
            ->assertHasNoFormErrors();
    }
}
