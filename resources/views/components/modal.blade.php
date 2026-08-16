@props(['name' => null, 'show' => false, 'wireModel' => null, 'onClose' => null])

<div
    x-data="{ open: @if($wireModel) @entangle($wireModel) @else @js((bool) $show) @endif }"
    @if($onClose)
        x-init="$watch('open', (value) => { if (!value) $wire.{{ $onClose }}() })"
    @endif
    x-on:open-modal.window="if ($event.detail === '{{ $name }}') open = true"
    x-on:close-modal.window="if (!$event.detail || $event.detail === '{{ $name }}') open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
>
    <div x-show="open" x-transition.opacity x-on:click="open = false" class="fixed inset-0 bg-black/50"></div>

    <div
        x-show="open"
        x-transition
        {{ $attributes->merge(['class' => 'relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl']) }}
    >
        {{ $slot }}
    </div>
</div>
