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

    public function test_a_negative_value_is_rejected_for_either_type(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateCoupon::class)
            ->fillForm(['code' => 'NEGATIVE', 'type' => CouponType::Percentage->value, 'value' => -10])
            ->call('create')
            ->assertHasFormErrors(['value' => 'min']);
    }

    public function test_a_percentage_value_over_100_is_rejected(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateCoupon::class)
            ->fillForm(['code' => 'OVER100', 'type' => CouponType::Percentage->value, 'value' => 150])
            ->call('create')
            ->assertHasFormErrors(['value' => 'max']);
    }

    public function test_a_fixed_value_has_no_upper_bound_in_the_form(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateCoupon::class)
            ->fillForm(['code' => 'BIGFIXED', 'type' => CouponType::Fixed->value, 'value' => 500])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_negative_usage_limits_are_rejected(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateCoupon::class)
            ->fillForm([
                'code' => 'BADLIMIT',
                'type' => CouponType::FreeShipping->value,
                'usage_limit' => 0,
                'usage_limit_per_user' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['usage_limit' => 'min', 'usage_limit_per_user' => 'min']);
    }

    public function test_a_negative_minimum_order_amount_is_rejected(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateCoupon::class)
            ->fillForm(['code' => 'BADMIN', 'type' => CouponType::FreeShipping->value, 'min_order_amount' => -5])
            ->call('create')
            ->assertHasFormErrors(['min_order_amount' => 'min']);
    }
}
