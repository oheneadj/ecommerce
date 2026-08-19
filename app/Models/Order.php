<?php

/**
 * A customer order, created once from a cart at checkout.
 */

declare(strict_types=1);

namespace App\Models;

use App\Concerns\DisplaysInStoreTimezone;
use App\Concerns\HasFormattedMoney;
use App\Concerns\HasUlid;
use App\Concerns\LogsAdminActivity;
use App\Enums\OrderStatus;
use App\Observers\OrderObserver;
use Carbon\CarbonInterface;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Created once, permanently, by CreateOrderFromCart. `user_id` is never
 * auto-populated from a matching `guest_email` — a guest order only ever
 * gets attached to an account via ClaimGuestOrder, which requires the
 * customer to authenticate first (BRD FR-3.2a). Every OrderItem's display
 * must read only from its own `item_snapshot`, never live Product/
 * ProductVariant data, so a past order is unaffected by later catalog edits.
 * The same rule applies to `address_snapshot` — frozen at checkout by
 * CreateOrderFromCart, so a past order's shipping details never change
 * even if the live `Address` is later edited or deleted. `address_id` is
 * nullable and `nullOnDelete()` for exactly that reason: deleting the
 * address (e.g. cascading from a customer deleting their account) must
 * never be blocked by, or corrupt, historical order data.
 *
 * @property int $id
 * @property string $ulid
 * @property int|null $cart_id
 * @property string $order_number
 * @property int|null $user_id
 * @property string|null $guest_email
 * @property string|null $guest_phone
 * @property int|null $address_id
 * @property array<string, string|null>|null $address_snapshot
 * @property int|null $shipping_method_id
 * @property string|null $shipping_method_name
 * @property int|null $coupon_id
 * @property OrderStatus $status
 * @property int $subtotal
 * @property int $discount_total
 * @property int $tax_total
 * @property int $shipping_total
 * @property int $grand_total
 * @property string|null $invoice_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'cart_id', 'user_id', 'guest_email', 'guest_phone', 'address_id', 'address_snapshot',
    'shipping_method_id', 'shipping_method_name', 'coupon_id', 'status',
    'subtotal', 'discount_total', 'tax_total', 'shipping_total', 'grand_total', 'invoice_path',
])]
#[ObservedBy(OrderObserver::class)]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use DisplaysInStoreTimezone, HasFactory, HasFormattedMoney, HasUlid, LogsAdminActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'address_snapshot' => 'array',
        ];
    }

    /**
     * The account this order belongs to, if any (null for a guest order).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The cart this order was created from — a cart converts into at most one order.
     *
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * The live Address record this order's shipping was captured from —
     * may be null (the address was later deleted, e.g. via account
     * deletion). Never used for display; use `address_snapshot` instead.
     *
     * @return BelongsTo<Address, $this>
     */
    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    /**
     * The coupon applied to this order, if any.
     *
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * The live ShippingMethod record chosen at checkout — may be null (the
     * method was later deleted). Never used for display; use
     * `shipping_method_name` instead, same reason as `address_snapshot`.
     *
     * @return BelongsTo<ShippingMethod, $this>
     */
    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    /**
     * This order's line items.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * The audit trail of this order's status changes.
     *
     * @return HasMany<OrderStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    /**
     * Every payment attempt made against this order.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * This order's shipment, if one has been assigned.
     *
     * @return HasOne<Shipment, $this>
     */
    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    /**
     * The order's grand total formatted for display (e.g. "GH₵150.00").
     */
    public function getGrandTotalFormattedAttribute(): string
    {
        return $this->formattedMoney($this->grand_total);
    }

    public function getSubtotalFormattedAttribute(): string
    {
        return $this->formattedMoney($this->subtotal);
    }

    public function getDiscountTotalFormattedAttribute(): string
    {
        return $this->formattedMoney($this->discount_total);
    }

    public function getShippingTotalFormattedAttribute(): string
    {
        return $this->formattedMoney($this->shipping_total);
    }

    public function getTaxTotalFormattedAttribute(): string
    {
        return $this->formattedMoney($this->tax_total);
    }

    /**
     * `created_at` converted to the store's configured display timezone
     * (`StoreSetting::timezone`, default UTC) — everything is stored in
     * UTC per CLAUDE.md's rule, but showing a raw UTC timestamp to a
     * customer outside that offset can display their order as placed on
     * the wrong calendar day. Every customer-facing view (order history,
     * order detail, invoice PDF) should read this instead of `created_at`
     * directly.
     */
    public function getPlacedAtAttribute(): ?CarbonInterface
    {
        return $this->inStoreTimezone($this->created_at);
    }
}
