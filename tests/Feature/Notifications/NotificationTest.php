<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Actions\Cart\AddItemToCart;
use App\Actions\Checkout\CreateOrderFromCart;
use App\Actions\Inventory\AdjustStockWithReservationCheck;
use App\Actions\Inventory\CheckLowStockLevels;
use App\Actions\Inventory\RecordStockMovement;
use App\Actions\Order\AssignShipment;
use App\Actions\Payment\HandlePaymentWebhook;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Enums\StockReservationStatus;
use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\StockReservation;
use App\Models\User;
use App\Notifications\LowStockAlert;
use App\Notifications\OrderPlaced;
use App\Notifications\OrderShipped;
use App\Notifications\PaymentFailed;
use App\Notifications\PaymentSucceeded;
use App\Notifications\ReservationsAtRiskAlert;
use App\Payments\PaymentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\Feature\Payment\FakePaymentGateway;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        Role::findOrCreate(UserRole::StoreKeeper->value, 'web');
        Role::findOrCreate(UserRole::Admin->value, 'web');
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');

        FakePaymentGateway::reset();
        $this->app->make(PaymentManager::class)->extend('fake', fn () => new FakePaymentGateway);
        config(['payments.channels.mobile_money' => 'fake', 'payments.channels.card' => 'fake']);
    }

    private function storeKeeper(): User
    {
        Role::findOrCreate(UserRole::StoreKeeper->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::StoreKeeper->value);

        return $user;
    }

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_order_placed_notification_is_sent_to_registered_customer(): void
    {
        $user = User::factory()->create();
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        AddItemToCart::run($cart, $variant, 1);
        $address = Address::factory()->create(['user_id' => $user->id]);

        CreateOrderFromCart::run($cart, $address);

        Notification::assertSentTo($user, OrderPlaced::class);
    }

    public function test_order_placed_notification_is_sent_to_guest_via_email_and_sms(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        $cart = Cart::factory()->create(['user_id' => null, 'session_id' => 'guest']);
        AddItemToCart::run($cart, $variant, 1);
        $address = Address::factory()->create(['user_id' => null]);

        CreateOrderFromCart::run($cart, $address, guestEmail: 'guest@example.com', guestPhone: '0551234567');

        Notification::assertSentOnDemand(
            OrderPlaced::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routeNotificationFor('mail') === 'guest@example.com'
                && $notifiable->routeNotificationFor('sms') === '0551234567',
        );
    }

    public function test_notification_channel_falls_back_to_email_when_no_phone(): void
    {
        $user = User::factory()->create(['email' => 'googleonly@example.com', 'phone' => null]);
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        AddItemToCart::run($cart, $variant, 1);
        $address = Address::factory()->create(['user_id' => $user->id]);

        CreateOrderFromCart::run($cart, $address);

        Notification::assertSentTo(
            $user,
            OrderPlaced::class,
            fn ($notification, $channels) => in_array('mail', $channels, true) && ! in_array('sms', $channels, true),
        );
    }

    public function test_payment_succeeded_notification_is_sent_via_webhook(): void
    {
        $user = User::factory()->create();
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        $order = Order::factory()->create(['user_id' => $user->id]);
        $order->items()->create([
            'product_variant_id' => $variant->id,
            'item_snapshot' => ['product_name' => 'Test', 'sku' => $variant->sku],
            'unit_price' => 1000,
            'quantity' => 2,
        ]);
        StockReservation::factory()->create([
            'product_variant_id' => $variant->id,
            'order_id' => $order->id,
            'quantity' => 2,
            'status' => StockReservationStatus::Active,
        ]);
        Payment::factory()->create(['order_id' => $order->id, 'provider' => 'fake', 'provider_reference' => 'ref-1', 'status' => PaymentStatus::Pending]);

        $request = Request::create('/webhooks/payments/fake', 'POST', ['provider_reference' => 'ref-1', 'event_id' => 'evt-1']);
        HandlePaymentWebhook::run($request, 'fake');

        Notification::assertSentTo($user, PaymentSucceeded::class);
    }

    public function test_payment_failed_notification_is_sent_via_webhook(): void
    {
        FakePaymentGateway::$verifyStatus = PaymentStatus::Failed;
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        Payment::factory()->create(['order_id' => $order->id, 'provider' => 'fake', 'provider_reference' => 'ref-1', 'status' => PaymentStatus::Pending]);

        $request = Request::create('/webhooks/payments/fake', 'POST', ['provider_reference' => 'ref-1', 'event_id' => 'evt-1']);
        HandlePaymentWebhook::run($request, 'fake');

        Notification::assertSentTo($user, PaymentFailed::class);
    }

    public function test_order_shipped_notification_is_sent_on_shipment_assignment(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $method = ShippingMethod::factory()->create();

        AssignShipment::run($order, $method, 'TRACK123');

        Notification::assertSentTo($user, OrderShipped::class);
    }

    public function test_low_stock_alert_sent_to_store_keeper_when_stock_crosses_threshold(): void
    {
        $storeKeeper = $this->storeKeeper();
        $variant = ProductVariant::factory()->create(['stock' => 6, 'low_stock_threshold' => 5]);

        RecordStockMovement::run($variant, StockMovementType::Sale, -2);

        Notification::assertSentTo($storeKeeper, LowStockAlert::class);
    }

    public function test_low_stock_alert_not_resent_while_already_below_threshold(): void
    {
        $storeKeeper = $this->storeKeeper();
        $variant = ProductVariant::factory()->create(['stock' => 3, 'low_stock_threshold' => 5]);

        RecordStockMovement::run($variant, StockMovementType::Sale, -1);

        Notification::assertNotSentTo($storeKeeper, LowStockAlert::class);
    }

    public function test_reservations_at_risk_alert_sent_to_admin_after_adjustment(): void
    {
        $admin = $this->admin();
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        StockReservation::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity' => 8,
            'status' => StockReservationStatus::Active,
        ]);
        $actor = User::factory()->create();

        AdjustStockWithReservationCheck::run($variant, -9, $actor);

        Notification::assertSentTo($admin, ReservationsAtRiskAlert::class);
    }

    public function test_daily_sweep_notifies_store_keeper_for_all_low_stock_variants(): void
    {
        $storeKeeper = $this->storeKeeper();
        ProductVariant::factory()->create(['stock' => 2, 'low_stock_threshold' => 5]);
        ProductVariant::factory()->create(['stock' => 20, 'low_stock_threshold' => 5]);

        $count = CheckLowStockLevels::run();

        $this->assertSame(1, $count);
        Notification::assertSentTo($storeKeeper, LowStockAlert::class);
    }
}
