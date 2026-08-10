@props([
    'label' => null,
    'type' => 'text',
    'viewable' => false,
])

@php
    // Callers bind via wire:model (plain, .live, .live.debounce.400ms,
    // etc.), never a plain `name` attribute — derive the error-bag key
    // from whichever wire:model* attribute is actually present. Without
    // this, $attributes->get('name') is always null, and @error(null)
    // matches *any* error anywhere on the page (MessageBag::has(null)
    // is defined as "has any error at all") — every <x-input> on the
    // page would show the first error in the whole bag, not its own.
    $fieldName = null;

    foreach ($attributes->getAttributes() as $attributeName => $attributeValue) {
        if ($attributeName === 'wire:model' || str_starts_with($attributeName, 'wire:model.')) {
            $fieldName = $attributeValue;
            break;
        }
    }
@endphp

<div class="w-full" @if($viewable) x-data="{ show: false }" @endif>
    @if($label)
        <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $label }}</label>
    @endif

    <div class="relative">
        <input
            @if($viewable)
                :type="show ? 'text' : 'password'"
            @else
                type="{{ $type }}"
            @endif
            {{ $attributes->merge(['class' => 'w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100']) }}
        />

        @if($viewable)
            <button type="button" x-on:click="show = !show" class="absolute inset-y-0 end-0 flex items-center pe-3 text-zinc-400 hover:text-zinc-600">
                <x-app-icon x-show="!show" name="eye" class="size-4" />
                <x-app-icon x-show="show" name="eye-slash" class="size-4" x-cloak />
            </button>
        @endif
    </div>

    @error($fieldName)
        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
