@props([
    'variant' => 'outline',
    'icon' => null,
    'iconFilled' => false,
    'type' => 'button',
    'href' => null,
])

@php
    $variants = [
        'primary' => 'bg-brand-primary text-white hover:bg-brand-secondary',
        'danger' => 'bg-red-600 text-white hover:bg-red-500',
        'outline' => 'border border-zinc-300 text-zinc-800 hover:bg-zinc-50 dark:border-zinc-600 dark:text-zinc-100 dark:hover:bg-zinc-800',
        'filled' => 'bg-zinc-200 text-zinc-800 hover:bg-zinc-300 dark:bg-zinc-700 dark:text-zinc-100 dark:hover:bg-zinc-600',
        'ghost' => 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800',
    ];

    $classes = 'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-all duration-150 ease-out active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100 cursor-pointer '
        .($variants[$variant] ?? $variants['outline']);
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<x-app-icon :name="$icon" :filled="$iconFilled" class="size-4" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<x-app-icon :name="$icon" :filled="$iconFilled" class="size-4" />@endif
        {{ $slot }}
    </button>
@endif
