<?php

/**
 * A shopping cart, owned by either a logged-in user or a guest session.
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\CartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Adding/removing items here never touches stock or creates a reservation
 * (BRD FR-3.1) — stock is only reserved once CreateOrderFromCart runs.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $session_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'session_id'])]
class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use HasFactory;

    /**
     * The account this cart belongs to, if any (null for a guest cart).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The items currently in this cart.
     *
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * The order this cart converted into, if checkout has already run.
     *
     * @return HasOne<Order, $this>
     */
    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    /**
     * Carts still usable for checkout — either never converted to an
     * order, or converted but every payment attempt on that order has
     * failed (nothing `Pending`/`Success`). A cart only becomes truly
     * "closed" once its order has a payment actually in flight or
     * settled — until then, retrying checkout should reuse the same
     * cart/order rather than silently starting a fresh, empty one and
     * orphaning the original order.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereDoesntHave('order')
                ->orWhereHas('order', function (Builder $query): void {
                    $query->whereDoesntHave('payments', function (Builder $query): void {
                        $query->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Success]);
                    });
                });
        });
    }
}
