<div class="space-y-6">
    <h1 class="text-2xl font-semibold">{{ __('My Cart') }}</h1>

    <x-card aria-hidden="true">
        <div class="divide-y divide-zinc-200">
            @for ($i = 0; $i < 3; $i++)
                <div class="flex animate-pulse items-center gap-4 p-4">
                    <div class="h-16 w-16 shrink-0 rounded-lg bg-zinc-200"></div>
                    <div class="flex-1 space-y-2">
                        <div class="h-4 w-1/2 rounded bg-zinc-200"></div>
                        <div class="h-3 w-1/4 rounded bg-zinc-200"></div>
                    </div>
                    <div class="h-8 w-24 rounded-lg bg-zinc-200"></div>
                    <div class="h-4 w-16 rounded bg-zinc-200"></div>
                </div>
            @endfor
        </div>

        <div class="mt-4 flex animate-pulse items-center justify-between border-t border-zinc-200 pt-4">
            <div class="h-5 w-20 rounded bg-zinc-200"></div>
            <div class="h-5 w-16 rounded bg-zinc-200"></div>
        </div>
    </x-card>
</div>
