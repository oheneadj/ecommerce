<?php

/**
 * Bulk-seeds customers and orders spread across every day, week, and month
 * from 2024-01-01 up to now, so dashboard charts (Orders YoY, Customer
 * Growth, Revenue, Top Products) have real historical data to render
 * instead of a handful of flat, same-day rows. Writes directly (not via
 * CreateOrderFromCart) since this is bulk test data, not a checkout replay
 * — created_at/updated_at are backdated with a raw update after insert.
 */

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HistoricalDataSeeder extends Seeder
{
    private const START_DATE = '2024-01-01';

    private const CUSTOMER_COUNT = 400;

    private const ORDER_COUNT = 2500;

    /**
     * Per-year sequence for order_number, keyed by the order's actual
     * (backdated) year — the OrderObserver's own sequence is keyed off
     * insertion-time `now()`, which collides once we rewrite `created_at`
     * to a historical date right after insert.
     *
     * Keys are numeric-string years, but PHP auto-casts numeric string
     * array keys to int at runtime, hence the int|string key type here.
     *
     * @var array<int|string, int>
     */
    private array $orderNumberSequences = [];

    public function run(): void
    {
        $variants = ProductVariant::query()->get();

        if ($variants->isEmpty()) {
            return;
        }

        $countsByYear = Order::query()
            ->get(['created_at'])
            ->groupBy(fn (Order $order) => $order->created_at->format('Y'))
            ->map(fn ($orders) => $orders->count());

        foreach ($countsByYear as $year => $count) {
            $this->orderNumberSequences[(string) $year] = $count;
        }

        $start = Carbon::parse(self::START_DATE)->startOfDay();
        $end = now();

        $this->command->getOutput()->writeln('Seeding historical customers...');
        $customers = $this->seedCustomers($start, $end);

        $this->command->getOutput()->writeln('Seeding historical orders...');
        $this->seedOrders($customers, $variants, $start, $end);
    }

    /**
     * @return Collection<int, User>
     */
    private function seedCustomers(CarbonInterface $start, CarbonInterface $end): Collection
    {
        $totalDays = (int) $start->diffInDays($end);
        $customers = collect();

        for ($i = 0; $i < self::CUSTOMER_COUNT; $i++) {
            $createdAt = $this->randomTimestamp($start, $totalDays);

            $user = User::factory()->create();
            $address = Address::factory()->create(['user_id' => $user->id, 'is_default' => true]);

            DB::table('users')->where('id', $user->id)->update([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
            DB::table('addresses')->where('id', $address->id)->update([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $user->created_at = \Illuminate\Support\Carbon::instance($createdAt);
            $customers->push($user);
        }

        return $customers->sortBy('created_at')->values();
    }

    /**
     * @param  Collection<int, User>  $customers
     * @param  Collection<int, ProductVariant>  $variants
     */
    private function seedOrders(Collection $customers, Collection $variants, CarbonInterface $start, CarbonInterface $end): void
    {
        $totalDays = (int) $start->diffInDays($end);

        for ($i = 0; $i < self::ORDER_COUNT; $i++) {
            $orderDate = $this->randomTimestamp($start, $totalDays);

            $eligibleCustomers = $customers->filter(fn (User $customer) => $customer->created_at->lte($orderDate));

            if ($eligibleCustomers->isEmpty()) {
                continue;
            }

            $customer = $eligibleCustomers->random();
            $status = $this->randomStatus($orderDate, $end);

            $orderVariants = $variants->random(random_int(1, 3));
            $subtotal = 0;

            $address = Address::factory()->create(['user_id' => $customer->id]);
            DB::table('addresses')->where('id', $address->id)->update([
                'created_at' => $orderDate,
                'updated_at' => $orderDate,
            ]);

            $order = Order::factory()->create([
                'user_id' => $customer->id,
                'address_id' => $address->id,
                'status' => $status,
                'subtotal' => 0,
                'grand_total' => 0,
                'order_number' => $this->nextOrderNumber($orderDate),
            ]);

            foreach ($orderVariants as $variant) {
                $quantity = random_int(1, 3);
                $lineTotal = $variant->price * $quantity;
                $subtotal += $lineTotal;

                $item = OrderItem::factory()->create([
                    'order_id' => $order->id,
                    'product_variant_id' => $variant->id,
                    'item_snapshot' => [
                        'product_name' => $variant->product->name,
                        'sku' => $variant->sku,
                    ],
                    'unit_price' => $variant->price,
                    'quantity' => $quantity,
                ]);

                DB::table('order_items')->where('id', $item->id)->update([
                    'created_at' => $orderDate,
                    'updated_at' => $orderDate,
                ]);
            }

            $order->forceFill(['subtotal' => $subtotal, 'grand_total' => $subtotal])->save();

            DB::table('orders')->where('id', $order->id)->update([
                'created_at' => $orderDate,
                'updated_at' => $orderDate,
            ]);

            $this->seedPayment($order, $status, $subtotal, $orderDate);
        }
    }

    private function seedPayment(Order $order, OrderStatus $status, int $amount, CarbonInterface $orderDate): void
    {
        $paymentDate = $orderDate->copy()->addMinutes(random_int(1, 30));

        $paymentStatus = match (true) {
            in_array($status, [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered], true) => PaymentStatus::Success,
            $status === OrderStatus::Cancelled => random_int(1, 100) <= 40 ? PaymentStatus::Failed : PaymentStatus::Pending,
            default => PaymentStatus::Pending,
        };

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'provider' => fake()->randomElement(['moolre', 'paystack']),
            'amount' => $amount,
            'status' => $paymentStatus,
        ]);

        DB::table('payments')->where('id', $payment->id)->update([
            'created_at' => $paymentDate,
            'updated_at' => $paymentDate,
        ]);

        if ($paymentStatus === PaymentStatus::Success
            && in_array($status, [OrderStatus::Delivered, OrderStatus::Shipped], true)
            && random_int(1, 100) <= 5) {
            $this->seedRefund($payment, $amount, $paymentDate);
        }
    }

    private function seedRefund(Payment $payment, int $amount, CarbonInterface $paymentDate): void
    {
        $refundDate = $paymentDate->copy()->addDays(random_int(1, 10));

        $refund = Refund::factory()->create([
            'payment_id' => $payment->id,
            'amount' => random_int((int) ($amount * 0.3), $amount),
            'status' => RefundStatus::Success,
        ]);

        DB::table('refunds')->where('id', $refund->id)->update([
            'created_at' => $refundDate,
            'updated_at' => $refundDate,
        ]);
    }

    private function nextOrderNumber(CarbonInterface $orderDate): string
    {
        $year = (string) $orderDate->format('Y');
        $this->orderNumberSequences[$year] = ($this->orderNumberSequences[$year] ?? 0) + 1;

        return sprintf('ORD-%s-%06d', $year, $this->orderNumberSequences[$year]);
    }

    private function randomTimestamp(CarbonInterface $start, int $totalDays): CarbonInterface
    {
        return $start->copy()
            ->addDays(random_int(0, $totalDays))
            ->setTime(random_int(0, 23), random_int(0, 59), random_int(0, 59));
    }

    /**
     * Only orders placed within the last 5 days may still be Pending or
     * Processing — a month-old order sitting in either state isn't
     * realistic and would flood the "Flagged Orders" widget.
     */
    private function randomStatus(CarbonInterface $orderDate, CarbonInterface $end): OrderStatus
    {
        if ($orderDate->diffInDays($end) <= 5) {
            return fake()->randomElement([
                OrderStatus::Pending,
                OrderStatus::Processing,
                OrderStatus::Paid,
                OrderStatus::Shipped,
                OrderStatus::Delivered,
            ]);
        }

        return fake()->randomElement([
            OrderStatus::Delivered,
            OrderStatus::Delivered,
            OrderStatus::Delivered,
            OrderStatus::Shipped,
            OrderStatus::Shipped,
            OrderStatus::Paid,
            OrderStatus::Cancelled,
        ]);
    }
}
