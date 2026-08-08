@props([
    'color' => 'gray',
])

@php
    $classes = match ($color) {
        'success' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        'warning' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'danger' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
        'info' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
        'primary' => 'bg-brand-primary/10 text-brand-primary',
        default => 'bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-300',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {$classes}"]) }}>
    {{ $slot }}
</span>
