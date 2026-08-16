<x-layouts::storefront :title="__('Access denied')">
    <div class="mx-auto max-w-xl space-y-6 py-12 text-center">
        <p class="text-sm font-medium text-brand-primary">{{ __('403') }}</p>
        <h1 class="text-2xl font-semibold">{{ __("You don't have access to this page") }}</h1>
        <p class="text-sm text-zinc-500">
            {{ __("You don't have permission to view this page.") }}
        </p>
        <div class="flex justify-center">
            <x-button variant="primary" href="{{ route('home') }}">{{ __('Back to homepage') }}</x-button>
        </div>
    </div>
</x-layouts::storefront>
