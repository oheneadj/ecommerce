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
        'outline' => 'border border-zinc-300 text-zinc-800 hover:bg-zinc-50',
        'filled' => 'bg-zinc-200 text-zinc-800 hover:bg-zinc-300',
        'ghost' => 'text-zinc-600 hover:bg-zinc-100',
        // Plain inline text links (e.g. "Use a different number", "Cancel")
        // that happen to trigger a Livewire action — not a boxed button, so
        // they skip the shared padding/border/rounded base below, but still
        // get the same wire:loading-disable treatment as every other variant.
        'link' => 'text-zinc-500 hover:underline',
        'link-primary' => 'font-medium text-brand-primary hover:underline',
    ];

    $isLink = in_array($variant, ['link', 'link-primary'], true);

    $classes = $isLink
        ? 'inline cursor-pointer text-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed '.$variants[$variant]
        : 'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-all duration-150 ease-out active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100 cursor-pointer '
            .($variants[$variant] ?? $variants['outline']);

    // Every wire:click (or wire:click.prevent/.stop) button, and every
    // type="submit" button (almost always inside a wire:submit form in
    // this app), automatically gets disabled-while-loading — the
    // disabled:* classes above already provide the dimmed/not-allowed
    // visual for free. This exists so individual call sites don't have
    // to keep re-adding wire:loading.attr="disabled" by hand (and
    // forgetting it, leaving a button double-clickable with no
    // feedback) — a caller that already sets its own wire:target is
    // respected as-is, not overridden.
    $wireClick = $attributes->get('wire:click') ?? $attributes->get('wire:click.prevent') ?? $attributes->get('wire:click.stop');
    $isLivewireAction = $wireClick !== null || $type === 'submit';
    $hasExplicitTarget = $attributes->has('wire:target');
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<x-app-icon :name="$icon" :filled="$iconFilled" class="size-4" />@endif
        {{ $slot }}
    </a>
@else
    <button
        type="{{ $type }}"
        @if($isLivewireAction)
            wire:loading.attr="disabled"
            @if($wireClick && ! $hasExplicitTarget)
                wire:target="{{ $wireClick }}"
            @endif
        @endif
        {{ $attributes->merge(['class' => $classes]) }}
    >
        @if($icon)<x-app-icon :name="$icon" :filled="$iconFilled" class="size-4" />@endif
        {{ $slot }}
    </button>
@endif
