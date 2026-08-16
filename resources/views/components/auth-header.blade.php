@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-center">
    <h1 class="text-xl font-semibold text-zinc-900">{{ $title }}</h1>
    <p class="text-sm text-zinc-500">{{ $description }}</p>
</div>
