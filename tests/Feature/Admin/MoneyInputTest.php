<?php

/**
 * Covers the Cedis-input-stores-pesewas conversion (MoneyInput) applied to
 * every money field in the admin panel — variant price, shipping cost,
 * payment refunds, and Coupon's min_order_amount/value (the latter only
 * conditionally money, depending on coupon type).
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\CouponType;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Enums\VariantStatus;
use App\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Filament\Resources\Coupons\Pages\EditCoupon;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Filament\Resources\ShippingMethods\Pages\CreateShippingMethod;
use App\Filament\Resources\ShippingMethods\Pages\EditShippingMethod;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Payments\PaymentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Feature\Payment\FakePaymentGateway;
use Tests\TestCase;

class MoneyInputTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FakePaymentGateway::reset();
        $this->app->make(PaymentManager::class)->extend('fake', fn () => new FakePaymentGateway);
    }

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    private function superAdmin(): User
    {
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::SuperAdmin->value);

        return $user;
    }

    public function test_creating_a_product_variant_with_a_cedis_price_stores_pesewas(): void
    {
        $this->actingAs($this->admin());

        $category = Category::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Kente Shirt',
                'slug' => 'kente-shirt',
                'category_id' => $category->id,
                'status' => ProductStatus::Active->value,
                'variants' => [
                    [
                        'sku' => 'SHIRT-M',
                        'price' => 30.50,
                        'stock' => 10,
                        'status' => VariantStatus::Active->value,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $variant = Product::query()->where('slug', 'kente-shirt')->sole()->variants->sole();
        $this->assertSame(3050, $variant->price);
    }

    public function test_editing_a_variant_displays_its_stored_price_in_cedis(): void
    {
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 3050]);

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->mountTableAction('edit', $variant)
            ->assertTableActionDataSet(['price' => 30.5]);
    }

    public function test_creating_a_shipping_method_with_a_cedis_cost_stores_pesewas(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateShippingMethod::class)
            ->fillForm(['name' => 'Express', 'cost' => 12.5])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1250, ShippingMethod::query()->where('name', 'Express')->sole()->cost);
    }

    public function test_editing_a_shipping_method_displays_its_stored_cost_in_cedis(): void
    {
        $this->actingAs($this->admin());

        $shippingMethod = ShippingMethod::factory()->create(['cost' => 1250]);

        Livewire::test(EditShippingMethod::class, ['record' => $shippingMethod->getRouteKey()])
            ->assertFormSet(['cost' => 12.5]);
    }

    public function test_shipping_method_cost_formatted_accessor(): void
    {
        $shippingMethod = ShippingMethod::factory()->create(['cost' => 1250]);

        $this->assertSame('GH₵12.50', $shippingMethod->cost_formatted);
    }

    public function test_issuing_a_refund_with_a_cedis_amount_stores_pesewas(): void
    {
        $this->actingAs($this->superAdmin());

        $payment = Payment::factory()->create(['provider' => 'fake', 'status' => PaymentStatus::Success, 'amount' => 5000]);

        Livewire::test(ListPayments::class)
            ->callTableAction('refund', $payment, data: ['amount' => 20])
            ->assertHasNoTableActionErrors();

        $this->assertSame(2000, $payment->refunds()->sole()->amount);
    }

    public function test_issuing_a_refund_with_a_zero_or_negative_amount_is_rejected_by_the_form(): void
    {
        $this->actingAs($this->superAdmin());

        $payment = Payment::factory()->create(['provider' => 'fake', 'status' => PaymentStatus::Success, 'amount' => 5000]);

        Livewire::test(ListPayments::class)
            ->callTableAction('refund', $payment, data: ['amount' => 0])
            ->assertHasTableActionErrors(['amount' => 'min']);

        $this->assertSame(0, $payment->refunds()->count());
    }

    public function test_creating_a_fixed_coupon_with_a_cedis_value_stores_pesewas(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateCoupon::class)
            ->fillForm([
                'code' => 'SAVE10',
                'type' => CouponType::Fixed->value,
                'value' => 10,
                'min_order_amount' => 50,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $coupon = Coupon::query()->where('code', 'SAVE10')->sole();
        $this->assertSame(1000, $coupon->value);
        $this->assertSame(5000, $coupon->min_order_amount);
    }

    public function test_creating_a_percentage_coupon_stores_its_value_unconverted(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateCoupon::class)
            ->fillForm([
                'code' => 'SAVE15PCT',
                'type' => CouponType::Percentage->value,
                'value' => 15,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $coupon = Coupon::query()->where('code', 'SAVE15PCT')->sole();
        $this->assertSame(15, $coupon->value);
    }

    public function test_editing_a_fixed_coupon_displays_its_value_in_cedis(): void
    {
        $this->actingAs($this->admin());

        $coupon = Coupon::factory()->create(['type' => CouponType::Fixed, 'value' => 1000]);

        Livewire::test(EditCoupon::class, ['record' => $coupon->getRouteKey()])
            ->assertFormSet(['value' => 10.0]);
    }

    public function test_editing_a_percentage_coupon_displays_its_value_unconverted(): void
    {
        $this->actingAs($this->admin());

        $coupon = Coupon::factory()->create(['type' => CouponType::Percentage, 'value' => 15]);

        Livewire::test(EditCoupon::class, ['record' => $coupon->getRouteKey()])
            ->assertFormSet(['value' => 15]);
    }
}
