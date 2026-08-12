<?php

/**
 * Covers the "Copy code" row action on the coupons table — a client-side
 * clipboard copy backed by a confirmation notification, so an admin can
 * quickly grab a coupon's code without opening the edit form.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Resources\Coupons\Pages\ListCoupons;
use App\Models\Coupon;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CouponCopyCodeActionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_copy_code_action_exists_for_each_coupon_row(): void
    {
        $this->actingAs($this->admin());
        $coupon = Coupon::factory()->create(['code' => 'SAVE20']);

        Livewire::test(ListCoupons::class)
            ->assertActionExists(TestAction::make('copyCode')->table($coupon));
    }

    public function test_copy_code_action_embeds_the_coupon_code_for_the_clipboard_write(): void
    {
        $this->actingAs($this->admin());
        Coupon::factory()->create(['code' => 'SAVE20']);

        Livewire::test(ListCoupons::class)
            ->assertSeeHtml('navigator.clipboard.writeText')
            ->assertSeeHtml('SAVE20')
            ->assertSeeHtml('FilamentNotification');
    }
}
