<x-layouts::storefront :title="__('Page not found')">
    <div class="mx-auto max-w-xl space-y-6 py-12 text-center">
        <p class="text-sm font-medium text-brand-primary">{{ __('404') }}</p>
        <h1 class="text-2xl font-semibold">{{ __('This page could not be found') }}</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            {{ __("The page you're looking for doesn't exist or may have moved.") }}
        </p>
        <div class="flex justify-center">
            <x-button variant="primary" href="{{ route('home') }}">{{ __('Back to homepage') }}</x-button>
        </div>
    </div>
</x-layouts::storefront>
