<div class="space-y-6" @if ($this->hasPendingPayment) wire:poll.3s="refreshOrder" @endif>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">{{ $order->order_number }}</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Placed on :date', ['date' => $order->created_at->format('d M Y, H:i')]) }}</p>
        </div>
        <x-status-badge :color="$order->status->getColor()">{{ $order->status->getLabel() }}</x-status-badge>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card>
                <h2 class="text-lg font-medium">{{ __('Items') }}</h2>
                <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($order->items as $item)
                        <div wire:key="order-item-{{ $item->id }}" class="flex items-center justify-between gap-4 py-3">
                            <div>
                                <p class="font-medium">{{ $item->item_snapshot['product_name'] ?? '—' }}</p>
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $item->item_snapshot['sku'] ?? '' }} &times; {{ $item->quantity }}</p>
                            </div>
                            <span class="font-medium">{{ $item->line_total_formatted }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 space-y-2 border-t border-zinc-200 pt-4 text-sm dark:border-zinc-700">
                    <div class="flex justify-between"><span>{{ __('Subtotal') }}</span><span>{{ $order->subtotal_formatted }}</span></div>
                    <div class="flex justify-between"><span>{{ __('Discount') }}</span><span>-{{ $order->discount_total_formatted }}</span></div>
                    <div class="flex justify-between"><span>{{ $order->shipping_method_name ?? __('Shipping') }}</span><span>{{ $order->shipping_total_formatted }}</span></div>
                    <div class="flex justify-between"><span>{{ __('Tax') }}</span><span>{{ $order->tax_total_formatted }}</span></div>
                    <div class="flex justify-between text-base font-semibold"><span>{{ __('Total') }}</span><span>{{ $order->grand_total_formatted }}</span></div>
                </div>
            </x-card>

            <x-card>
                <h2 class="text-lg font-medium">{{ __('Order status') }}</h2>
                @if ($order->statusHistories->isEmpty())
                    <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No status updates yet.') }}</p>
                @else
                    <ol class="mt-4 space-y-4">
                        @foreach ($order->statusHistories->sortByDesc('created_at') as $history)
                            <li wire:key="status-history-{{ $history->id }}" class="flex items-start gap-3">
                                <x-status-badge :color="$history->status->getColor()">{{ $history->status->getLabel() }}</x-status-badge>
                                <div>
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $history->created_at?->format('d M Y, H:i') }}</p>
                                    @if ($history->note)
                                        <p class="text-sm">{{ $history->note }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif

                @if ($order->shipment)
                    <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                        <p class="text-sm"><span class="font-medium">{{ __('Tracking number') }}:</span> {{ $order->shipment->tracking_number ?? '—' }}</p>
                    </div>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card>
                <h2 class="text-lg font-medium">{{ __('Delivery address') }}</h2>
                <p class="mt-2 text-sm">
                    {{ $order->address_snapshot['recipient_name'] ?? '' }}<br>
                    {{ $order->address_snapshot['phone'] ?? '' }}<br>
                    {{ $this->addressLines }}
                </p>
            </x-card>

            @if ($order->payments->isNotEmpty())
                <x-card>
                    <h2 class="text-lg font-medium">{{ __('Payment') }}</h2>
                    <div class="mt-4 space-y-3">
                        @foreach ($order->payments as $payment)
                            <div wire:key="payment-{{ $payment->id }}" class="flex items-center justify-between text-sm">
                                <span>{{ ucfirst($payment->provider) }}</span>
                                <x-status-badge :color="$payment->status->getColor()">{{ $payment->status->getLabel() }}</x-status-badge>
                            </div>
                        @endforeach
                    </div>

                    @if ($this->latestFailedPayment)
                        <x-button wire:click="retryPayment" wire:target="retryPayment" variant="primary" class="mt-4 w-full justify-center">
                            {{ __('Retry payment') }}
                        </x-button>
                        @error('retryPayment')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    @endif

                    @if ($this->canDownloadInvoice)
                        <x-button wire:click="downloadInvoice" wire:target="downloadInvoice" variant="outline" icon="document-duplicate" class="mt-4 w-full justify-center">
                            {{ __('Download invoice') }}
                        </x-button>
                    @endif
                </x-card>
            @endif
        </div>
    </div>
</div>
