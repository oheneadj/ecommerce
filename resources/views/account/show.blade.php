<x-layouts::storefront :title="__('My Account')">
    <x-account.layout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-semibold">{{ __('Welcome back') }}{{ auth()->user()->name ? ', '.auth()->user()->name : '' }}</h1>
            </div>

            <livewire:storefront.recent-orders />

            <x-card>
                <h2 class="flex items-center gap-2 text-lg font-medium">
                    <x-app-icon name="home" class="size-5 text-zinc-400" />
                    {{ __('Addresses') }}
                </h2>
                <p class="mt-2 text-sm text-zinc-500">{{ __('Manage the addresses used at checkout.') }}</p>
                <x-button href="{{ route('account.addresses') }}" wire:navigate variant="outline" class="mt-4">
                    {{ __('Manage addresses') }}
                </x-button>
            </x-card>

            <x-card>
                <h2 class="flex items-center gap-2 text-lg font-medium">
                    <x-app-icon name="cog" class="size-5 text-zinc-400" />
                    {{ __('Account settings') }}
                </h2>
                <p class="mt-2 text-sm text-zinc-500">{{ __('Update your name, email, password, and two-factor authentication.') }}</p>
                <x-button href="{{ route('profile.edit') }}" wire:navigate variant="outline" class="mt-4">
                    {{ __('Manage account settings') }}
                </x-button>
            </x-card>
        </div>
    </x-account.layout>
</x-layouts::storefront>
