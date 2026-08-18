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
                <p class="mt-1 text-sm text-zinc-500">{{ __('Update your name and email address') }}</p>

                <form wire:submit="updateProfileInformation" class="mt-6 w-full space-y-6">
                    <x-input wire:model="name" :label="__('Name')" type="text" placeholder="{{ __('e.g. Ama Owusu') }}" required autofocus autocomplete="name" />

                    <x-input wire:model="email" :label="__('Email')" type="email" placeholder="{{ __('you@example.com') }}" required autocomplete="email" />

                    <div class="flex items-center gap-4">
                        <x-button variant="primary" type="submit">{{ __('Save') }}</x-button>
                    </div>
                </form>
            </x-card>

            <x-card>
                <h2 class="flex items-center gap-2 text-lg font-medium">
                    <x-app-icon name="phone" class="size-5 text-zinc-400" />
                    {{ __('Phone number') }}
                </h2>

                @if ($this->verifiedPhone && ! $changingPhone)
                    <p class="mt-1 text-sm text-zinc-500">{{ __('Verified') }}: {{ $this->verifiedPhone }}</p>
                    <x-button variant="link-primary" wire:click="startPhoneChange" class="mt-4">
                        {{ __('Change number') }}
                    </x-button>
                @else
                    <p class="mt-1 text-sm text-zinc-500">
                        {{ $this->verifiedPhone
                            ? __('Verify a new number to replace :phone.', ['phone' => $this->verifiedPhone])
                            : __('Add and verify a phone number to also sign in with it, or receive SMS updates.') }}
                    </p>

                    @if (! $phoneCodeSent)
                        <form wire:submit="sendPhoneVerificationCode" class="mt-6 space-y-4">
                            <x-input wire:model="newPhone" :label="__('Phone number')" type="tel" placeholder="{{ __('+233201234567 or 0201234567') }}" />
                            <div class="flex items-center gap-4">
                                <x-button variant="primary" type="submit" class="whitespace-nowrap">{{ __('Send code') }}</x-button>
                                @if ($this->verifiedPhone)
                                    <x-button variant="link" wire:click="cancelPhoneChange">
                                        {{ __('Cancel') }}
                                    </x-button>
                                @endif
                            </div>
                        </form>
                    @else
                        <form wire:submit="verifyPhoneCode" class="mt-6 space-y-4">
                            <p class="text-sm text-zinc-500">{{ __('Enter the code sent to :phone.', ['phone' => $newPhone]) }}</p>
                            <x-input wire:model="phoneOtpCode" :label="__('Verification code')" type="text" inputmode="numeric" placeholder="{{ __('6-digit code') }}" autofocus />
                            <div class="flex items-center gap-4">
                                <x-button variant="primary" type="submit">{{ __('Verify') }}</x-button>
                                <x-button variant="link" wire:click="cancelPhoneVerification">
                                    {{ __('Use a different number') }}
                                </x-button>
                            </div>
                        </form>
                    @endif
                @endif
            </x-card>

            <livewire:settings.delete-user-form />
        </div>
    </x-account.layout>
</section>
