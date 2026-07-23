@props(['href' => null, 'icon' => null])

@php
    $classes = 'flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-start text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800';
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<x-app-icon :name="$icon" class="size-4" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $attributes->get('type', 'button') }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<x-app-icon :name="$icon" class="size-4" />@endif
        {{ $slot }}
    </button>
@endif
