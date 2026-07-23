<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <div x-data="{ mobileOpen: false }" class="flex min-h-screen">
            <aside
                x-cloak
                x-bind:class="mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
                class="fixed inset-y-0 start-0 z-40 flex w-64 flex-col border-e border-zinc-200 bg-zinc-50 transition-transform lg:sticky lg:translate-x-0 dark:border-zinc-700 dark:bg-zinc-900"
            >
                <div class="flex items-center justify-between p-4">
                    <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                    <button type="button" x-on:click="mobileOpen = false" class="lg:hidden">
                        <x-app-icon name="chevron-down" class="size-5 -rotate-90" />
                    </button>
                </div>

                <nav class="flex-1 space-y-1 px-2">
                    <p class="px-2 pb-1 text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Platform') }}</p>
                    <a
                        href="{{ route('dashboard') }}"
                        wire:navigate
                        class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium {{ request()->routeIs('dashboard') ? 'bg-zinc-200 text-zinc-900 dark:bg-zinc-700 dark:text-white' : 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800' }}"
                    >
                        <x-app-icon name="home" class="size-4" />
                        {{ __('Dashboard') }}
                    </a>
                </nav>

                <nav class="space-y-1 px-2 pb-2">
                    <a href="https://github.com/laravel/livewire-starter-kit" target="_blank" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                        <x-app-icon name="folder" class="size-4" />
                        {{ __('Repository') }}
                    </a>
                    <a href="https://laravel.com/docs/starter-kits#livewire" target="_blank" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                        <x-app-icon name="book-open" class="size-4" />
                        {{ __('Documentation') }}
                    </a>
                </nav>

                <div class="hidden border-t border-zinc-200 p-2 lg:block dark:border-zinc-700">
                    <x-desktop-user-menu />
                </div>
            </aside>

            <div x-show="mobileOpen" x-cloak x-on:click="mobileOpen = false" class="fixed inset-0 z-30 bg-black/30 lg:hidden"></div>

            <div class="flex flex-1 flex-col lg:ps-0">
                <!-- Mobile header -->
                <header class="flex items-center gap-2 border-b border-zinc-200 p-4 lg:hidden dark:border-zinc-700">
                    <button type="button" x-on:click="mobileOpen = true">
                        <x-app-icon name="bars" class="size-5" />
                    </button>

                    <div class="flex-1"></div>

                    <x-dropdown align="end">
                        <x-slot:trigger>
                            <button type="button" class="flex items-center gap-2 rounded-lg px-2 py-1.5">
                                <span class="flex size-8 items-center justify-center rounded-full bg-zinc-200 text-xs font-medium text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">
                                    {{ auth()->user()->initials() }}
                                </span>
                                <x-app-icon name="chevron-down" class="size-4 text-zinc-400" />
                            </button>
                        </x-slot:trigger>

                        <div class="flex items-center gap-2 px-2 py-1.5 text-start text-sm">
                            <span class="flex size-8 items-center justify-center rounded-full bg-zinc-200 text-xs font-medium text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">
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
                </header>

                <main class="flex-1 p-4 lg:p-6">
                    {{ $slot }}
                </main>
            </div>

            <x-toast-container />
        </div>
    </body>
</html>
