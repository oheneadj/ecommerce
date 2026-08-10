<section class="w-full">
    <h2 class="sr-only">{{ __('Appearance settings') }}</h2>

    <x-account.layout>
        <div class="space-y-6">
            <h1 class="text-2xl font-semibold">{{ __('Appearance') }}</h1>

            <x-card>
                <h2 class="flex items-center gap-2 text-lg font-medium">
                    <x-app-icon name="sun" class="size-5 text-zinc-400" />
                    {{ __('Appearance') }}
                </h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Update the appearance settings for your account') }}</p>

                <div
                    x-data="{
                        appearance: localStorage.getItem('appearance') ?? 'system',
                        apply(value) {
                            this.appearance = value;
                            localStorage.setItem('appearance', value);
                            const isDark = value === 'dark' || (value === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                            document.documentElement.classList.toggle('dark', isDark);
                        },
                    }"
                    class="mt-4 inline-flex rounded-lg border border-zinc-300 p-1 dark:border-zinc-600"
                >
                    @foreach (['light' => ['sun', __('Light')], 'dark' => ['moon', __('Dark')], 'system' => ['computer-desktop', __('System')]] as $value => [$icon, $label])
                        <button
                            wire:key="appearance-option-{{ $value }}"
                            type="button"
                            x-on:click="apply('{{ $value }}')"
                            x-bind:class="appearance === '{{ $value }}' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 dark:text-zinc-300'"
                            class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium"
                        >
                            <x-app-icon :name="$icon" class="size-4" />
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </x-card>
        </div>
    </x-account.layout>
</section>
