<div>
    <x-card>
        <h2 class="flex items-center gap-2 text-lg font-medium">
            <x-app-icon name="trash" class="size-5 text-zinc-400" />
            {{ __('Delete account') }}
        </h2>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Delete your account and all of its resources') }}</p>

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
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Are you sure you want to delete your account?') }}</h2>

                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                </p>
            </div>

            <x-input wire:model="password" :label="__('Password')" type="password" viewable />

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <x-modal-close>
                    <x-button variant="filled">{{ __('Cancel') }}</x-button>
                </x-modal-close>

                <x-button variant="danger" type="submit">{{ __('Delete account') }}</x-button>
            </div>
        </form>
    </x-modal>
</div>
