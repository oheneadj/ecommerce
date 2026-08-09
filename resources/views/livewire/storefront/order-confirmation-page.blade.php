<div class="mx-auto max-w-xl space-y-6 text-center">
    <div class="flex justify-center">
        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-green-600 dark:bg-green-900/40 dark:text-green-400">
            <x-app-icon name="check" class="size-8" />
        </div>
    </div>

    <div>
        <h1 class="text-2xl font-semibold">{{ __('Thank you for your order!') }}</h1>
        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
            {{ __("We've received order :number and will let you know as soon as your payment is confirmed.", ['number' => $order->order_number]) }}
        </p>
    </div>

    <x-card>
        <div class="flex items-center justify-between text-sm">
            <span class="text-zinc-500 dark:text-zinc-400">{{ __('Order number') }}</span>
            <span class="font-medium">{{ $order->order_number }}</span>
        </div>
        <div class="mt-2 flex items-center justify-between text-sm">
            <span class="text-zinc-500 dark:text-zinc-400">{{ __('Total') }}</span>
            <span class="font-medium">{{ $order->grand_total_formatted }}</span>
        </div>
        <div class="mt-2 flex items-center justify-between text-sm">
            <span class="text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</span>
            <x-status-badge :color="$order->status->getColor()">{{ $order->status->getLabel() }}</x-status-badge>
        </div>
    </x-card>

    <div class="flex justify-center gap-3">
        <x-button variant="primary" href="{{ route('account.orders.show', $order) }}">{{ __('Track this order') }}</x-button>
        <x-button href="{{ route('home') }}">{{ __('Continue shopping') }}</x-button>
    </div>
</div>
