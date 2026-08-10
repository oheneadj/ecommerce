<section class="w-full">
    <h2 class="sr-only">{{ __('Security settings') }}</h2>

    <x-account.layout>
        <div class="space-y-6">
            <h1 class="text-2xl font-semibold">{{ __('Security') }}</h1>

            <x-card>
                <h2 class="flex items-center gap-2 text-lg font-medium">
                    <x-app-icon name="lock-closed" class="size-5 text-zinc-400" />
                    {{ __('Update password') }}
                </h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Ensure your account is using a long, random password to stay secure') }}</p>

                <form method="POST" wire:submit="updatePassword" class="mt-6 space-y-6">
                    <x-input
                        wire:model="current_password"
                        :label="__('Current password')"
                        type="password"
                        required
                        autocomplete="current-password"
                        viewable
                    />
                    <x-input
                        wire:model="password"
                        :label="__('New password')"
                        type="password"
                        required
                        autocomplete="new-password"
                        passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                        viewable
                    />
                    <x-input
                        wire:model="password_confirmation"
                        :label="__('Confirm password')"
                        type="password"
                        required
                        autocomplete="new-password"
                        passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                        viewable
                    />

                    <div class="flex items-center gap-4">
                        <x-button variant="primary" type="submit" data-test="update-password-button">{{ __('Save') }}</x-button>
                    </div>
                </form>
            </x-card>

            @if ($canManageTwoFactor)
                <x-card>
                    <h2 class="flex items-center gap-2 text-lg font-medium">
                        <x-app-icon name="finger-print" class="size-5 text-zinc-400" />
                        {{ __('Two-factor authentication') }}
                    </h2>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Manage your two-factor authentication settings') }}</p>

                    <div class="mt-4 flex flex-col w-full space-y-6 text-sm" wire:cloak>
                        @if ($twoFactorEnabled)
                            <div class="space-y-4">
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('You will be prompted for a secure, random pin during login, which you can retrieve from the TOTP-supported application on your phone.') }}
                                </p>

                                <div class="flex justify-start">
                                    <x-button
                                        variant="danger"
                                        wire:click="disable"
                                    >
                                        {{ __('Disable 2FA') }}
                                    </x-button>
                                </div>

                                <livewire:settings.two-factor.recovery-codes :$requiresConfirmation />
                            </div>
                        @else
                            <div class="space-y-4">
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from a TOTP-supported application on your phone.') }}
                                </p>

                                <x-button
                                    variant="primary"
                                    wire:click="enable"
                                >
                                    {{ __('Enable 2FA') }}
                                </x-button>
                            </div>
                        @endif
                    </div>
                </x-card>
            @endif

        @if ($canManageTwoFactor)
            <x-modal
                name="two-factor-setup-modal"
                class="max-w-md md:min-w-md"
                on-close="closeModal"
                wire-model="showModal"
            >
                <div class="space-y-6">
                    <div class="flex flex-col items-center space-y-4">
                        <div class="p-0.5 w-auto rounded-full border border-stone-100 dark:border-stone-600 bg-white dark:bg-stone-800 shadow-sm">
                            <div class="p-2.5 rounded-full border border-stone-200 dark:border-stone-600 overflow-hidden bg-stone-100 dark:bg-stone-200 relative">
                                <div class="flex items-stretch absolute inset-0 w-full h-full divide-x [&>div]:flex-1 divide-stone-200 dark:divide-stone-300 justify-around opacity-50">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <div></div>
                                    @endfor
                                </div>

                                <div class="flex flex-col items-stretch absolute w-full h-full divide-y [&>div]:flex-1 inset-0 divide-stone-200 dark:divide-stone-300 justify-around opacity-50">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <div></div>
                                    @endfor
                                </div>

                                <x-app-icon name="qr-code" class="relative z-20 dark:text-accent-foreground"/>
                            </div>
                        </div>

                        <div class="space-y-2 text-center">
                            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $this->modalConfig['title'] }}</h2>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $this->modalConfig['description'] }}</p>
                        </div>
                    </div>

                    @if ($showVerificationStep)
                        <div class="space-y-6">
                            <div
                                class="flex flex-col items-center space-y-3 justify-center"
                                x-data
                                x-init="$nextTick(() => $el.querySelector('input')?.focus())"
                            >
                                <x-otp-input
                                    name="code"
                                    length="6"
                                    wire-model="code"
                                    class="mx-auto"
                                />
                            </div>

                            <div class="flex items-center space-x-3">
                                <x-button
                                    variant="outline"
                                    class="flex-1"
                                    wire:click="resetVerification"
                                >
                                    {{ __('Back') }}
                                </x-button>

                                <x-button
                                    variant="primary"
                                    class="flex-1"
                                    wire:click="confirmTwoFactor"
                                    x-bind:disabled="$wire.code.length < 6"
                                >
                                    {{ __('Confirm') }}
                                </x-button>
                            </div>
                        </div>
                    @else
                        @error('setupData')
                            <x-callout variant="danger" icon="x-circle" heading="{{ $message }}"/>
                        @enderror

                        <div class="flex justify-center">
                            <div class="relative w-64 overflow-hidden border rounded-lg border-stone-200 dark:border-stone-700 aspect-square">
                                @empty($qrCodeSvg)
                                    <div class="absolute inset-0 flex items-center justify-center bg-white dark:bg-stone-700 animate-pulse">
                                        <x-app-icon name="loading"/>
                                    </div>
                                @else
                                <div x-data class="flex items-center justify-center h-full p-4">
                                    <div
                                        class="bg-white p-3 rounded"
                                        :style="document.documentElement.classList.contains('dark') ? 'filter: invert(1) brightness(1.5)' : ''"
                                    >
                                            {!! $qrCodeSvg !!}
                                        </div>
                                    </div>
                                @endempty
                            </div>
                        </div>

                        <div>
                            <x-button
                                :disabled="$errors->has('setupData')"
                                variant="primary"
                                class="w-full"
                                wire:click="showVerificationIfNecessary"
                            >
                                {{ $this->modalConfig['buttonText'] }}
                            </x-button>
                        </div>

                        <div class="space-y-4">
                            <div class="relative flex items-center justify-center w-full">
                                <div class="absolute inset-0 w-full h-px top-1/2 bg-stone-200 dark:bg-stone-600"></div>
                                <span class="relative px-2 text-sm bg-white dark:bg-stone-800 text-stone-600 dark:text-stone-400">
                                    {{ __('or, enter the code manually') }}
                                </span>
                            </div>

                            <div
                                class="flex items-center space-x-2"
                                x-data="{
                                    copied: false,
                                    async copy() {
                                        try {
                                            await navigator.clipboard.writeText('{{ $manualSetupKey }}');
                                            this.copied = true;
                                            setTimeout(() => this.copied = false, 1500);
                                        } catch (e) {
                                            console.warn('Could not copy to clipboard');
                                        }
                                    }
                                }"
                            >
                                <div class="flex items-stretch w-full border rounded-xl dark:border-stone-700">
                                    @empty($manualSetupKey)
                                        <div class="flex items-center justify-center w-full p-3 bg-stone-100 dark:bg-stone-700">
                                            <x-app-icon name="loading" class="size-4"/>
                                        </div>
                                    @else
                                        <input
                                            type="text"
                                            readonly
                                            value="{{ $manualSetupKey }}"
                                            class="w-full p-3 bg-transparent outline-none text-stone-900 dark:text-stone-100"
                                        />

                                        <button
                                            @click="copy()"
                                            class="px-3 transition-colors border-l cursor-pointer border-stone-200 dark:border-stone-600"
                                        >
                                            <x-app-icon name="document-duplicate" x-show="!copied" class="size-4"></x-icon>
                                            <x-app-icon
                                                name="check"
                                                x-show="copied"
                                                class="size-4 text-green-500"
                                            ></x-icon>
                                        </button>
                                    @endempty
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </x-modal>
        @endif

        @if ($canManagePasskeys)
            <x-card>
                <h2 class="flex items-center gap-2 text-lg font-medium">
                    <x-app-icon name="key" class="size-5 text-zinc-400" />
                    {{ __('Passkeys') }}
                </h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Manage your passkeys for passwordless sign-in') }}</p>

                <div class="mt-4 flex flex-col w-full space-y-6 text-sm" wire:cloak>
                    <div class="border rounded-lg border-zinc-200 dark:border-zinc-700 overflow-hidden">
                        @forelse ($passkeys as $passkey)
                            <div class="flex items-center justify-between p-4 {{ ! $loop->last ? 'border-b border-zinc-200 dark:border-zinc-700' : '' }}">
                                <div class="flex items-center gap-4">
                                    <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-800">
                                        <x-app-icon name="key" class="size-5 text-zinc-500 dark:text-zinc-400" />
                                    </div>
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2.5">
                                            <p class="font-medium tracking-tight">{{ $passkey['name'] }}</p>
                                            @if ($passkey['authenticator'])
                                                <x-badge>{{ $passkey['authenticator'] }}</x-badge>
                                            @endif
                                        </div>
                                        <p class="text-zinc-500 dark:text-zinc-400 text-xs">
                                            {{ __('Added :time', ['time' => $passkey['created_at_diff']]) }}
                                            @if ($passkey['last_used_at_diff'])
                                                <span class="opacity-50 mx-1">/</span>
                                                {{ __('Last used :time', ['time' => $passkey['last_used_at_diff']]) }}
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                <x-button
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="confirmDelete({{ $passkey['id'] }})"
                                    class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/50"
                                />
                            </div>
                        @empty
                            <div class="p-8 text-center">
                                <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-zinc-100 dark:bg-zinc-800">
                                    <x-app-icon name="key" class="size-7 text-zinc-400 dark:text-zinc-500" />
                                </div>
                                <p class="font-medium">{{ __('No passkeys yet') }}</p>
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Add a passkey to sign in without a password') }}</p>
                            </div>
                        @endforelse
                    </div>

                    <x-passkey-registration />
                </div>
            </x-card>
        @endif
        </div>
    </x-account.layout>

    <x-modal
        name="delete-passkey-modal"
        class="max-w-md md:min-w-md"
        on-close="closeDeleteModal"
        wire-model="showDeleteModal"
    >
        <div class="space-y-6">
            <div class="space-y-2">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Remove passkey') }}</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Are you sure you want to remove the passkey ":name"? You will no longer be able to use it to sign in.', ['name' => $deletingPasskeyName]) }}
                </p>
            </div>

            <div class="flex gap-3 justify-end">
                <x-button
                    variant="outline"
                    wire:click="closeDeleteModal"
                >
                    {{ __('Cancel') }}
                </x-button>
                <x-button
                    variant="danger"
                    wire:click="deletePasskey"
                >
                    {{ __('Remove passkey') }}
                </x-button>
            </div>
        </div>
    </x-modal>
</section>
