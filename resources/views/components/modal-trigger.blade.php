@props(['name'])

<span x-data x-on:click="$dispatch('open-modal', '{{ $name }}')">
    {{ $slot }}
</span>
