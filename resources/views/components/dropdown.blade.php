@props(['align' => 'start'])

<div x-data="{ open: false }" x-on:click.outside="open = false" class="relative">
    <div x-on:click="open = !open">
        {{ $trigger }}
    </div>

    <div
        x-show="open"
        x-cloak
        x-transition
        x-on:click="open = false"
        {{ $attributes->merge(['class' => 'absolute z-50 mt-2 min-w-56 rounded-lg border border-zinc-200 bg-white p-1 shadow-lg '.($align === 'end' ? 'end-0' : 'start-0')]) }}
    >
        {{ $slot }}
    </div>
</div>
