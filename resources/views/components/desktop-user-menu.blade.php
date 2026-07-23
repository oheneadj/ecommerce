<x-dropdown {{ $attributes }} align="start">
    <x-slot:trigger>
        <button type="button" data-test="sidebar-menu-button" class="flex w-full items-center gap-2 rounded-lg p-2 text-start hover:bg-zinc-100 dark:hover:bg-zinc-800">
            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-zinc-200 text-xs font-medium text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">
                {{ auth()->user()->initials() }}
            </span>
            <span class="grid flex-1 text-start text-sm leading-tight">
                <span class="truncate font-medium text-zinc-900 dark:text-zinc-100">{{ auth()->user()->name }}</span>
            </span>
            <x-app-icon name="chevrons-up-down" class="size-4 text-zinc-400" />
        </button>
    </x-slot:trigger>

    <div class="flex items-center gap-2 px-2 py-1.5 text-start text-sm">
        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-zinc-200 text-xs font-medium text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">
            {{ auth()->user()->initials() }}
        </span>
        <div class="grid flex-1 text-start text-sm leading-tight">
            <span class="truncate font-medium text-zinc-900 dark:text-zinc-100">{{ auth()->user()->name }}</span>
            <span class="truncate text-zinc-500 dark:text-zinc-400">{{ auth()->user()->email }}</span>
        </div>
    </div>

    <hr class="my-1 border-zinc-200 dark:border-zinc-700" />

    <x-menu-item :href="route('profile.edit')" icon="cog" wire:navigate>
        {{ __('Settings') }}
    </x-menu-item>

    <hr class="my-1 border-zinc-200 dark:border-zinc-700" />

    <form method="POST" action="{{ route('logout') }}" class="w-full">
        @csrf
        <x-menu-item type="submit" icon="arrow-right-start" class="w-full cursor-pointer" data-test="logout-button">
            {{ __('Log out') }}
        </x-menu-item>
    </form>
</x-dropdown>
