@props([
    'sidebar' => false,
])

<a {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    <span class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
        <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
    </span>
    <span class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ config('app.name', 'Laravel') }}</span>
</a>
