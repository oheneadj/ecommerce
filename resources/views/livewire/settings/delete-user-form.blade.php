<div>
    <x-card>
        <h2 class="flex items-center gap-2 text-lg font-medium">
            <x-app-icon name="trash" class="size-5 text-zinc-400" />
            {{ __('Delete account') }}
        </h2>
        <p class="mt-1 text-sm text-zinc-500">{{ __('Delete your account and all of its resources') }}</p>

        <div class="mt-4">
            <x-modal-trigger name="confirm-user-deletion">
                <x-button variant="danger">
                    {{ __('Delete account') }}
                </x-button>
            </x-modal-trigger>
        </div>
    </x-card>

    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" class="max-w-lg">
        <form method="POST" wire:submit="deleteUser" class="space-y-6">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900">{{ __('Are you sure you want to delete your account?') }}</h2>

                <p class="text-sm text-zinc-500">
                    @if ($this->hasPassword)
                        {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                    @elseif ($this->otpChannel === 'phone')
                        {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Send a verification code to your phone to confirm.') }}
                    @elseif ($this->otpChannel === 'mail')
                        {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Send a verification code to your email to confirm.') }}
                    @else
                        {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Type DELETE below to confirm.') }}
                    @endif
                </p>
            </div>

            @if ($this->hasPassword)
                <x-input wire:model="password" :label="__('Password')" type="password" viewable />
            @elseif ($this->canReceiveCode)
                @if (! $otpSent)
                    <x-button type="button" variant="filled" wire:click="sendDeletionCode">
                        {{ __('Send verification code') }}
                    </x-button>
                @else
                    <x-input wire:model="otpCode" :label="__('Verification code')" type="text" inputmode="numeric" placeholder="{{ __('6-digit code') }}" autofocus />
                    <button type="button" class="text-sm text-zinc-500 underline" wire:click="sendDeletionCode">
                        {{ __('Resend code') }}
                    </button>
                @endif
            @else
                <x-input wire:model="confirmationPhrase" :label="__('Type DELETE to confirm')" type="text" autofocus />
            @endif

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <x-modal-close>
                    <x-button variant="filled">{{ __('Cancel') }}</x-button>
                </x-modal-close>

                <x-button variant="danger" type="submit">{{ __('Delete account') }}</x-button>
            </div>
        </form>
    </x-modal>
</div>
