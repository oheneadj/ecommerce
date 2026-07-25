<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $order->order_number }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { padding: 6px 8px; border-bottom: 1px solid #ddd; text-align: left; }
        .totals td { border-bottom: none; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <h1>Invoice {{ $order->order_number }}</h1>
    <p>
        Date: {{ $order->created_at->format('d M Y') }}<br>
        Customer: {{ $order->user->name ?? $order->guest_email ?? 'Guest' }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>SKU</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Line Total</th>
            </tr>
        </thead>
        <tbody>
            {{-- Read exclusively from each item's own permanent snapshot —
                 never the live Product/ProductVariant tables, so this PDF
                 renders identically no matter what's since changed in the
                 catalog. --}}
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->item_snapshot['product_name'] ?? '—' }}</td>
                    <td>{{ $item->item_snapshot['sku'] ?? '—' }}</td>
                    <td class="text-right">{{ $item->unit_price_formatted }}</td>
                    <td class="text-right">{{ $item->quantity }}</td>
                    <td class="text-right">{{ $item->line_total_formatted }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="totals">
            <tr>
                <td colspan="4" class="text-right">Subtotal</td>
                <td class="text-right">{{ $order->subtotal_formatted }}</td>
            </tr>
            <tr>
                <td colspan="4" class="text-right">Discount</td>
                <td class="text-right">-{{ $order->discount_total_formatted }}</td>
            </tr>
            <tr>
                <td colspan="4" class="text-right">Shipping</td>
                <td class="text-right">{{ $order->shipping_total_formatted }}</td>
            </tr>
            <tr>
                <td colspan="4" class="text-right">Tax</td>
                <td class="text-right">{{ $order->tax_total_formatted }}</td>
            </tr>
            <tr>
                <td colspan="4" class="text-right"><strong>Total</strong></td>
                <td class="text-right"><strong>{{ $order->grand_total_formatted }}</strong></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
