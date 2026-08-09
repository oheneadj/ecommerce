@props(['product'])

@php
    $variant = $product->variants->first();
    $image = $product->images->firstWhere('is_primary', true)
        ?? $product->images->first()
        ?? $variant?->images->firstWhere('is_primary', true)
        ?? $variant?->images->first();
@endphp

<a href="{{ route('products.show', $product) }}" wire:navigate class="group block overflow-hidden rounded-lg border border-zinc-200 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-lg dark:border-zinc-700 dark:hover:border-zinc-600">
    <div class="flex aspect-square items-center justify-center overflow-hidden bg-zinc-100 dark:bg-zinc-800">
        @if ($image)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->path) }}" alt="{{ $product->name }}" loading="lazy" class="h-full w-full object-cover transition-transform duration-300 ease-out group-hover:scale-105">
        @else
            <x-app-icon name="folder" class="size-10 text-zinc-400" />
        @endif
    </div>

    <div class="p-3">
        <p class="truncate text-sm font-medium">{{ $product->name }}</p>
        @if ($variant)
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $variant->price_formatted }}</p>
        @endif
    </div>
</a>
