<?php

/**
 * A single dispatch of an order's items to the customer.
 */

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUlid;
use App\Enums\ShipmentStatus;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Created/updated by AssignShipment. A single-location, single-shipment
 * model — no multi-parcel splitting (matches FR-2.4's single physical
 * location assumption).
 *
 * @property int $id
 * @property string $ulid
 * @property int $order_id
 * @property int $shipping_method_id
 * @property string|null $tracking_number
 * @property string $status
 * @property Carbon|null $dispatched_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['order_id', 'shipping_method_id', 'tracking_number', 'status', 'dispatched_at'])]
class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use HasFactory, HasUlid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'dispatched_at' => 'datetime',
        ];
    }

    /**
     * The order this shipment fulfills.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The shipping method used for this shipment.
     *
     * @return BelongsTo<ShippingMethod, $this>
     */
    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }
}
