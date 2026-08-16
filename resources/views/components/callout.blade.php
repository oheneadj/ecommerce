@props(['variant' => 'default', 'icon' => null, 'heading' => null])

@php
    $variants = [
        'danger' => 'border-red-200 bg-red-50 text-red-800',
        'default' => 'border-zinc-200 bg-zinc-50 text-zinc-800',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'flex items-start gap-2 rounded-lg border p-3 text-sm '.($variants[$variant] ?? $variants['default'])]) }}>
    @if($icon)
        <x-app-icon :name="$icon" class="size-4 shrink-0" />
    @endif
    <div>
        @if($heading)
            <p class="font-medium">{{ $heading }}</p>
        @endif
        {{ $slot }}
    </div>
</div>
