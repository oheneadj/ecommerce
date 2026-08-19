<?php

/**
 * Covers the customer-facing order detail/tracking page
 * (/account/orders/{order}).
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Storefront\OrderDetailPage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\User;
use App\Payments\PaymentManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Payment\FakePaymentGateway;
use Tests\TestCase;

class OrderDetailPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $order = Order::factory()->create();

        $this->get("/account/orders/{$order->ulid}")->assertRedirect('/login');
    }

    public function test_a_customer_can_view_their_own_order_with_items_status_and_payments(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'address_snapshot' => [
                'recipient_name' => 'Ama Boateng',
                'phone' => '0244000000',
                'line1' => '12 Ring Road',
                'city' => 'Accra',
                'region' => 'Greater Accra',
            ],
        ]);
        $item = OrderItem::factory()->create(['order_id' => $order->id, 'item_snapshot' => ['product_name' => 'Blue Sneakers', 'sku' => 'SKU-1']]);
        OrderStatusHistory::factory()->create(['order_id' => $order->id, 'note' => 'Order received']);
        Payment::factory()->create(['order_id' => $order->id]);
        $this->actingAs($user);

        $this->get("/account/orders/{$order->ulid}")
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Blue Sneakers')
            ->assertSee('Ama Boateng')
            ->assertSee('Order received');
    }

    public function test_a_customer_cannot_view_another_customers_order(): void
    {
        $user = User::factory()->create();
        $otherOrder = Order::factory()->create();
        $this->actingAs($user);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(OrderDetailPage::class, ['orderUlid' => $otherOrder->ulid]);
    }

    private function enableFakeProvider(): void
    {
        FakePaymentGateway::reset();
        $this->app->make(PaymentManager::class)->extend('moolre', fn () => new FakePaymentGateway);
        DB::table('payment_provider_settings')->insert([
            'provider' => 'moolre',
            'enabled' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_failed_payment_shows_a_retry_button(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        Payment::factory()->create(['order_id' => $order->id, 'provider' => 'moolre', 'status' => PaymentStatus::Failed]);
        $this->actingAs($user);

        $this->get("/account/orders/{$order->ulid}")->assertSee('Retry payment');
    }

    public function test_a_successful_payment_shows_no_retry_button(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        Payment::factory()->create(['order_id' => $order->id, 'provider' => 'moolre', 'status' => PaymentStatus::Success]);
        $this->actingAs($user);

        $this->get("/account/orders/{$order->ulid}")->assertDontSee('Retry payment');
    }

    /**
     * Regression: an order whose first payment attempt failed, then whose
     * retry (or a late webhook confirmation on a second attempt) actually
     * succeeded, previously still showed "Retry payment" — latestFailedPayment()
     * found the earlier Failed row regardless of the later Success one. Both
     * payment rows still show in the list (an accurate record of what
     * happened), but the button must reflect the *current* state, not stale
     * history.
     */
    public function test_a_failed_payment_superseded_by_a_later_success_shows_no_retry_button(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        Payment::factory()->create(['order_id' => $order->id, 'provider' => 'moolre', 'status' => PaymentStatus::Failed]);
        Payment::factory()->create(['order_id' => $order->id, 'provider' => 'moolre', 'status' => PaymentStatus::Success]);
        $this->actingAs($user);

        $this->get("/account/orders/{$order->ulid}")
            ->assertSee('Failed')
            ->assertSee('Success')
            ->assertDontSee('Retry payment');
    }

    /**
     * Regression: a payment settles asynchronously (webhook, or
     * VerifyPendingPayments' polling fallback) well after this page has
     * already rendered — previously the customer had to manually reload to
     * see the order move past "Pending". wire:poll only needs to be present
     * while a payment is actually still Pending.
     */
    public function test_a_pending_payment_polls_for_updates(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        Payment::factory()->create(['order_id' => $order->id, 'provider' => 'moolre', 'status' => PaymentStatus::Pending]);
        $this->actingAs($user);

        $this->get("/account/orders/{$order->ulid}")->assertSeeHtml('wire:poll.3s="refreshOrder"');
    }

    public function test_a_resolved_order_does_not_poll(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        Payment::factory()->create(['order_id' => $order->id, 'provider' => 'moolre', 'status' => PaymentStatus::Success]);
        $this->actingAs($user);

        $this->get("/account/orders/{$order->ulid}")->assertDontSeeHtml('wire:poll.3s="refreshOrder"');
    }

    public function test_refreshing_the_order_picks_up_a_status_change_made_elsewhere(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Pending]);
        Payment::factory()->create(['order_id' => $order->id, 'provider' => 'moolre', 'status' => PaymentStatus::Pending]);
        $this->actingAs($user);

        $component = Livewire::test(OrderDetailPage::class, ['orderUlid' => $order->ulid]);

        // Simulates what a webhook does in the background, independent of
        // this already-rendered component's in-memory $order property.
        $order->update(['status' => OrderStatus::Paid]);
        Payment::query()->where('order_id', $order->id)->update(['status' => PaymentStatus::Success]);

        $component->call('refreshOrder')->assertSee(OrderStatus::Paid->getLabel());
    }

    public function test_retrying_a_failed_payment_starts_a_new_attempt_on_the_same_order(): void
    {
        $this->enableFakeProvider();
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        Payment::factory()->create(['order_id' => $order->id, 'provider' => 'moolre', 'status' => PaymentStatus::Failed]);
        $this->actingAs($user);

        Livewire::test(OrderDetailPage::class, ['orderUlid' => $order->ulid])
            ->call('retryPayment')
            ->assertRedirect('https://fake-gateway.test/pay/fake-ref-1');

        $this->assertSame(2, $order->fresh()->payments()->count());
        $this->assertSame(1, Order::query()->count());
    }

    public function test_retrying_when_the_order_is_already_paid_does_nothing(): void
    {
        $this->enableFakeProvider();
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Paid]);
        Payment::factory()->create(['order_id' => $order->id, 'provider' => 'moolre', 'status' => PaymentStatus::Failed]);
        $this->actingAs($user);

        Livewire::test(OrderDetailPage::class, ['orderUlid' => $order->ulid])
            ->call('retryPayment')
            ->assertNoRedirect();

        $this->assertSame(1, $order->fresh()->payments()->count());
    }

    public function test_retrying_with_no_failed_payment_does_nothing(): void
    {
        $this->enableFakeProvider();
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        Payment::factory()->create(['order_id' => $order->id, 'provider' => 'moolre', 'status' => PaymentStatus::Pending]);
        $this->actingAs($user);

        Livewire::test(OrderDetailPage::class, ['orderUlid' => $order->ulid])
            ->call('retryPayment')
            ->assertNoRedirect();

        $this->assertSame(1, $order->fresh()->payments()->count());
    }

    public function test_a_paid_orders_invoice_can_be_downloaded(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $path = 'invoices/ORD-DOWNLOAD-TEST.pdf';
        Storage::disk('local')->put($path, 'fake-pdf-content');
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Paid, 'invoice_path' => $path]);
        Payment::factory()->create(['order_id' => $order->id, 'status' => PaymentStatus::Success]);
        $this->actingAs($user);

        Livewire::test(OrderDetailPage::class, ['orderUlid' => $order->ulid])
            ->assertSee('Download invoice')
            ->call('downloadInvoice')
            ->assertFileDownloaded("{$order->order_number}.pdf");
    }

    public function test_an_unpaid_orders_invoice_button_is_hidden(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Pending, 'invoice_path' => null]);
        $this->actingAs($user);

        Livewire::test(OrderDetailPage::class, ['orderUlid' => $order->ulid])
            ->assertDontSee('Download invoice');
    }

    /**
     * Bug hunt regression: canDownloadInvoice() previously also required
     * status === Paid exactly, so the button vanished the moment a paid
     * order moved on to Processing/Shipped/Delivered — even though the
     * invoice file was still sitting there. Matches
     * OrderRecordActions::downloadInvoice()'s own gate (invoice_path
     * !== null alone).
     */
    public function test_a_shipped_orders_invoice_can_still_be_downloaded(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $path = 'invoices/ORD-SHIPPED-TEST.pdf';
        Storage::disk('local')->put($path, 'fake-pdf-content');
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Shipped, 'invoice_path' => $path]);
        Payment::factory()->create(['order_id' => $order->id, 'status' => PaymentStatus::Success]);
        $this->actingAs($user);

        Livewire::test(OrderDetailPage::class, ['orderUlid' => $order->ulid])
            ->assertSee('Download invoice')
            ->call('downloadInvoice')
            ->assertFileDownloaded("{$order->order_number}.pdf");
    }

    /**
     * A customer must never be able to trigger a download via a direct
     * component method call just because the button happens to be
     * server-side reachable — the same status/invoice_path guard the
     * button's visibility relies on is re-checked inside the action
     * itself, exactly like every other "don't trust the button was
     * clickable" guard already in this class (retryPayment).
     */
    public function test_downloading_an_unpaid_orders_invoice_is_rejected(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Pending, 'invoice_path' => null]);
        $this->actingAs($user);

        $response = Livewire::test(OrderDetailPage::class, ['orderUlid' => $order->ulid])
            ->call('downloadInvoice');

        $response->assertNoRedirect();
    }

    /**
     * Regression: `invoice_path` being set doesn't guarantee the file is
     * still actually on disk — regenerated on the fly instead of a raw
     * 404, mirroring the admin panel's own resilience fallback.
     */
    public function test_downloading_a_paid_orders_invoice_whose_file_is_missing_regenerates_it(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Paid, 'invoice_path' => 'invoices/ORD-MISSING.pdf']);
        Storage::disk('local')->assertMissing($order->invoice_path);
        $this->actingAs($user);

        Livewire::test(OrderDetailPage::class, ['orderUlid' => $order->ulid])
            ->call('downloadInvoice');

        Storage::disk('local')->assertExists($order->fresh()->invoice_path);
    }
}
