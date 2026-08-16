<div class="space-y-6">
    <h1 class="text-2xl font-semibold">{{ __('My Orders') }}</h1>

    <x-card aria-hidden="true">
        <div class="divide-y divide-zinc-200">
            @for ($i = 0; $i < 3; $i++)
                <div class="flex animate-pulse items-center justify-between gap-4 py-3">
                    <div class="space-y-2">
                        <div class="h-4 w-32 rounded bg-zinc-200"></div>
                        <div class="h-3 w-20 rounded bg-zinc-200"></div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="h-4 w-16 rounded bg-zinc-200"></div>
                        <div class="h-6 w-20 rounded-full bg-zinc-200"></div>
                    </div>
                </div>
            @endfor
        </div>
    </x-card>
</div>
