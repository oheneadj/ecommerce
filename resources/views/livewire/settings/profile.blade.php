<section class="w-full">
    <h2 class="sr-only">{{ __('Profile settings') }}</h2>

    <x-account.layout>
        <div class="space-y-6">
            <h1 class="text-2xl font-semibold">{{ __('Profile') }}</h1>

            <x-card>
                <h2 class="flex items-center gap-2 text-lg font-medium">
                    <x-app-icon name="user" class="size-5 text-zinc-400" />
                    {{ __('Profile') }}
                </h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Update your name and email address') }}</p>

                <form wire:submit="updateProfileInformation" class="mt-6 w-full space-y-6">
                    <x-input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

                    <div>
                        <x-input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                        @if ($this->hasUnverifiedEmail)
                            <div>
                                <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Your email address is unverified.') }}

                                    <a class="cursor-pointer text-sm hover:underline" wire:click.prevent="resendVerificationNotification">
                                        {{ __('Click here to re-send the verification email.') }}
                                    </a>
                                </p>
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center gap-4">
                        <x-button variant="primary" type="submit">{{ __('Save') }}</x-button>
                    </div>
                </form>
            </x-card>

            @if ($this->showDeleteUser)
                <livewire:settings.delete-user-form />
            @endif
        </div>
    </x-account.layout>
</section>
