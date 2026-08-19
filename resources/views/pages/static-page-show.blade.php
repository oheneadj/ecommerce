<x-layouts::storefront :title="$page->meta_title ?: $page->title" :og-description="$page->meta_description">
    <div class="mx-auto max-w-3xl space-y-6">
        <h1 class="text-2xl font-semibold">{{ $page->title }}</h1>

        <x-card>
            <div class="space-y-4 text-sm leading-relaxed text-zinc-700">
                {!! $page->content !!}
            </div>
        </x-card>
    </div>
</x-layouts::storefront>
