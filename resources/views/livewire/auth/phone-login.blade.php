<div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in')" :description="__('We\'ll text you a one-time code, no password needed')" />

        @if (! $codeSent)
            <form wire:submit="sendCode" class="flex flex-col gap-6">
                <x-input
                    wire:model="phone"
                    name="phone"
                    :label="__('Phone number')"
                    type="tel"
                    required
                    autofocus
                    placeholder="+233..."
                />

                <x-button variant="primary" type="submit" class="w-full">
                    {{ __('Send code') }}
                </x-button>
            </form>
        @else
            <form wire:submit="verify" class="flex flex-col gap-6">
                <p class="text-center text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Enter the 6-digit code sent to :phone', ['phone' => $phone]) }}
                </p>

                <x-otp-input name="code" length="6" wire-model="code" />

                @error('code')
                    <p class="text-center text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <x-button variant="primary" type="submit" class="w-full">
                    {{ __('Verify & log in') }}
                </x-button>

                <button type="button" wire:click="$set('codeSent', false)" class="text-center text-sm text-zinc-500 hover:underline dark:text-zinc-400">
                    {{ __('Use a different number') }}
                </button>
            </form>
        @endif

        <x-button variant="outline" icon="google" :href="route('login.google')" class="w-full">
            {{ __('Continue with Google') }}
        </x-button>

        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Prefer email and password?') }}</span>
            <a class="hover:underline" href="{{ route('login') }}" wire:navigate>{{ __('Log in another way') }}</a>
        </div>
    </div>

