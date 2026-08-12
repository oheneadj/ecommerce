@props(['class' => 'h-9 w-auto'])

@php
    // Same fetch-a-fresh-singleton pattern already used by partials/head.blade.php
    // and layouts/storefront.blade.php for this exact model — not a business
    // computation, just resolving the current deployment's settings row.
    $store ??= \App\Models\StoreSetting::current();
@endphp

@if ($store->logo_path)
    <img
        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($store->logo_path) }}"
        alt="{{ $store->business_name ?: config('app.name', 'Laravel') }}"
        {{ $attributes->merge(['class' => $class]) }}
    >
@else
    <span {{ $attributes->merge(['class' => 'text-lg font-semibold text-brand-primary']) }}>
        {{ $store->business_name ?: config('app.name', 'Laravel') }}
    </span>
@endif
