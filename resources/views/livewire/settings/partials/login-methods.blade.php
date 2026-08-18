<x-card>
    <h2 class="flex items-center gap-2 text-lg font-medium">
        <x-app-icon name="lock-closed" class="size-5 text-zinc-400" />
        {{ __('Other ways to sign in') }}
    </h2>
    <p class="mt-1 text-sm text-zinc-500">{{ __('Add another way to sign in to this account.') }}</p>

    <div class="mt-6 space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <x-app-icon name="google" class="size-5" />
                <span class="text-sm font-medium">{{ __('Google') }}</span>
            </div>

            @if ($this->hasGoogleLinked)
                <x-status-badge color="success">{{ __('Connected') }}</x-status-badge>
            @else
                <x-button variant="outline" :href="route('login.google', ['redirect_to' => url()->current()])">
                    {{ __('Connect') }}
                </x-button>
            @endif
        </div>

        <div>
            <div class="flex items-center gap-3">
                <x-app-icon name="lock-closed" class="size-4 text-zinc-400" />
                <span class="text-sm font-medium">{{ __('Password') }}</span>
            </div>

            @if ($this->hasPassword)
                <p class="mt-1 text-sm text-zinc-500">{{ __('You can already sign in with your email and password.') }}</p>
            @elseif (! $this->canSetPassword)
                <p class="mt-1 text-sm text-zinc-500">{{ __('Verify your email above to enable password sign-in.') }}</p>
            @else
                <form wire:submit="setInitialPassword" class="mt-4 space-y-4">
                    <x-input wire:model="newAccountPassword" :label="__('New password')" type="password" placeholder="{{ __('Enter a password') }}" viewable />
                    <x-input wire:model="newAccountPassword_confirmation" :label="__('Confirm password')" type="password" placeholder="{{ __('Re-enter the password') }}" viewable />
                    <x-button variant="primary" type="submit">{{ __('Set password') }}</x-button>
                </form>
            @endif
        </div>
    </div>
</x-card>
