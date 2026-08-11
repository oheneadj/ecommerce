<div class="space-y-6">
    <h1 class="text-2xl font-semibold">{{ __('Checkout') }}</h1>

    <div class="grid gap-6 lg:grid-cols-3" aria-hidden="true">
        <div class="space-y-6 lg:col-span-2">
            @for ($i = 0; $i < 3; $i++)
                <x-card class="animate-pulse space-y-3">
                    <div class="h-4 w-1/3 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                    <div class="h-10 rounded-lg bg-zinc-200 dark:bg-zinc-700"></div>
                    <div class="h-10 rounded-lg bg-zinc-200 dark:bg-zinc-700"></div>
                </x-card>
            @endfor
        </div>

        <div>
            <x-card class="animate-pulse space-y-3">
                <div class="h-4 w-1/2 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                <div class="h-3 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                <div class="h-3 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                <div class="h-3 w-2/3 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                <div class="mt-4 h-10 rounded-lg bg-zinc-200 dark:bg-zinc-700"></div>
            </x-card>
        </div>
    </div>
</div>
