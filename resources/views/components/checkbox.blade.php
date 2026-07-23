@props(['label' => null, 'checked' => false])

<label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
    <input
        type="checkbox"
        @checked($checked)
        {{ $attributes->merge(['class' => 'rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-800']) }}
    />
    @if($label)
        <span>{{ $label }}</span>
    @endif
</label>
