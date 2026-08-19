<x-layouts::auth.simple :title="$title ?? null" :noindex="$noindex ?? false">
    {{ $slot }}
</x-layouts::auth.simple>
