@props(['name', 'length' => 6, 'model' => null, 'wireModel' => null])

<input
    type="text"
    inputmode="numeric"
    autocomplete="one-time-code"
    placeholder="{{ str_repeat('•', $length) }}"
    maxlength="{{ $length }}"
    name="{{ $name }}"
    x-on:input="$el.value = $el.value.replace(/\D/g, '').slice(0, {{ $length }})"
    @if ($model) x-model="{{ $model }}" @endif
    @if ($wireModel) wire:model="{{ $wireModel }}" @endif
    {{ $attributes->merge(['class' => 'w-full rounded-lg border border-zinc-300 px-4 py-2.5 text-center text-lg font-medium tracking-[0.5em] focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100']) }}
/>
