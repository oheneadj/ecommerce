@props(['size' => 'md'])

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-700']) }}>
    {{ $slot }}
</span>
