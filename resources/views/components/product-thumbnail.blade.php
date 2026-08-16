@props(['variant', 'product' => null])

@php
    $product ??= $variant->product;
    $image = $variant->images->firstWhere('is_primary', true)
        ?? $variant->images->first()
        ?? $product->images->firstWhere('is_primary', true)
        ?? $product->images->first();
@endphp

@if ($image)
    <img
        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->path) }}"
        alt="{{ $product->name }}"
        loading="lazy"
        {{ $attributes->merge(['class' => 'rounded-lg object-cover']) }}
    >
@else
    <div {{ $attributes->merge(['class' => 'flex items-center justify-center rounded-lg bg-zinc-100 text-zinc-400']) }}>
        <x-app-icon name="folder" class="size-6" />
    </div>
@endif
