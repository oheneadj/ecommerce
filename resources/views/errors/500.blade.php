<x-layouts::storefront :title="__('Something went wrong')">
    <div class="mx-auto max-w-xl space-y-6 py-12 text-center">
        <p class="text-sm font-medium text-brand-primary">{{ __('500') }}</p>
        <h1 class="text-2xl font-semibold">{{ __('Something went wrong on our end') }}</h1>
        <p class="text-sm text-zinc-500">
            {{ __("We're already looking into it. Please try again shortly.") }}
        </p>
        <div class="flex justify-center">
            <x-button variant="primary" href="{{ route('home') }}">{{ __('Back to homepage') }}</x-button>
        </div>
    </div>
</x-layouts::storefront>
