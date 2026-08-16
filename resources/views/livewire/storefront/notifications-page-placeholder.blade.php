<div class="space-y-6">
    <h1 class="text-2xl font-semibold">{{ __('Notifications') }}</h1>

    <x-card aria-hidden="true">
        <div class="divide-y divide-zinc-200">
            @for ($i = 0; $i < 4; $i++)
                <div class="animate-pulse space-y-2 py-3">
                    <div class="h-4 w-1/3 rounded bg-zinc-200"></div>
                    <div class="h-3 w-2/3 rounded bg-zinc-200"></div>
                    <div class="h-3 w-16 rounded bg-zinc-200"></div>
                </div>
            @endfor
        </div>
    </x-card>
</div>
