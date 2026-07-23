<section class="w-full">
    @include('partials.settings-heading')

    <h2 class="sr-only">{{ __('Appearance settings') }}</h2>

    <x-settings.layout :heading="__('Appearance')" :subheading=" __('Update the appearance settings for your account')">
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
            class="inline-flex rounded-lg border border-zinc-300 p-1 dark:border-zinc-600"
        >
            @foreach (['light' => ['sun', __('Light')], 'dark' => ['moon', __('Dark')], 'system' => ['computer-desktop', __('System')]] as $value => [$icon, $label])
                <button
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
    </x-settings.layout>
</section>
