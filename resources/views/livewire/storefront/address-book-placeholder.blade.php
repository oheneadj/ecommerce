<div class="space-y-6" aria-hidden="true">
    <div class="flex animate-pulse items-center justify-between">
        <div class="h-8 w-40 rounded bg-zinc-200"></div>
        <div class="h-9 w-32 rounded-lg bg-zinc-200"></div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        @for ($i = 0; $i < 2; $i++)
            <x-card>
                <div class="animate-pulse space-y-2">
                    <div class="h-4 w-1/2 rounded bg-zinc-200"></div>
                    <div class="h-3 w-2/3 rounded bg-zinc-200"></div>
                    <div class="h-3 w-1/3 rounded bg-zinc-200"></div>
                    <div class="h-3 w-3/4 rounded bg-zinc-200"></div>
                </div>
                <div class="mt-4 flex gap-2">
                    <div class="h-8 w-16 animate-pulse rounded-lg bg-zinc-200"></div>
                    <div class="h-8 w-24 animate-pulse rounded-lg bg-zinc-200"></div>
                </div>
            </x-card>
        @endfor
    </div>
</div>
