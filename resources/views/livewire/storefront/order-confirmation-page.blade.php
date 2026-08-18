<div class="mx-auto max-w-xl space-y-6 text-center" @if ($this->hasPendingPayment) wire:poll.3s="refreshOrder" @endif>
    @if ($this->hasPendingPayment)
        <div class="flex justify-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                <x-app-icon name="loading" class="size-8" />
            </div>
        </div>

        <div>
            <h1 class="text-2xl font-semibold">{{ __('Confirming your payment...') }}</h1>
            <p class="mt-2 text-sm text-zinc-500">
                {{ __("We're waiting on confirmation from your payment provider — this page will update automatically.") }}
            </p>
        </div>
    @elseif ($this->latestFailedPayment)
        <div class="flex justify-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-red-100 text-red-600">
                <x-app-icon name="x-circle" class="size-8" />
            </div>
        </div>

        <div>
            <h1 class="text-2xl font-semibold">{{ __("Your payment didn't go through") }}</h1>
            <p class="mt-2 text-sm text-zinc-500">
                {{ $this->latestFailedPayment->metadata['error'] ?? __('Something went wrong starting your payment.') }}
            </p>
        </div>

        <x-button wire:click="retryPayment" wire:target="retryPayment" variant="primary">
            {{ __('Retry payment') }}
        </x-button>
        @error('retryPayment')
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror
    @else
        <div class="flex justify-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-green-600">
                <x-app-icon name="check" class="size-8" />
            </div>
        </div>

        <div>
            <h1 class="text-2xl font-semibold">{{ __('Thank you for your order!') }}</h1>
            <p class="mt-2 text-sm text-zinc-500">
                {{ __("We've received order :number and will let you know as soon as your payment is confirmed.", ['number' => $order->order_number]) }}
            </p>
        </div>
    @endif

    <x-card>
        <div class="flex items-center justify-between text-sm">
            <span class="text-zinc-500">{{ __('Order number') }}</span>
            <span class="font-medium">{{ $order->order_number }}</span>
        </div>
        <div class="mt-2 flex items-center justify-between text-sm">
            <span class="text-zinc-500">{{ __('Total') }}</span>
            <span class="font-medium">{{ $order->grand_total_formatted }}</span>
        </div>
        <div class="mt-2 flex items-center justify-between text-sm">
            <span class="text-zinc-500">{{ __('Status') }}</span>
            <x-status-badge :color="$order->status->getColor()">{{ $order->status->getLabel() }}</x-status-badge>
        </div>
    </x-card>

    <div class="flex justify-center gap-3">
        @auth
            <x-button variant="primary" href="{{ route('account.orders.show', $order) }}">{{ __('Track this order') }}</x-button>
        @endauth
        <x-button :variant="auth()->guest() ? 'primary' : 'outline'" href="{{ route('home') }}">{{ __('Continue shopping') }}</x-button>
    </div>

    @guest
        <p class="text-sm text-zinc-500">
            {{ __('Create an account to track this order and speed up your next checkout.') }}
            <a href="{{ route('login.phone') }}" class="font-medium text-brand-primary hover:underline">{{ __('Sign up') }}</a>
        </p>
    @endguest
</div>
