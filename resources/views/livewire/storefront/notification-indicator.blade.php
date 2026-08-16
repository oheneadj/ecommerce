<div class="relative" x-data x-on:click.outside="$wire.open = false">
    <button
        type="button"
        wire:click="toggle"
        class="relative flex items-center text-zinc-700 hover:text-brand-primary"
        aria-label="{{ __('Notifications') }}"
    >
        <x-app-icon name="bell" class="size-5" />
        @if ($this->unreadCount > 0)
            <span class="absolute -top-2 -right-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-brand-primary px-1 text-xs font-semibold text-white">
                {{ $this->unreadCount }}
            </span>
        @endif
    </button>

    @if ($open)
        <div class="absolute right-0 z-20 mt-2 w-80 rounded-lg border border-zinc-200 bg-white p-4 shadow-lg">
            @if ($this->recent->isEmpty())
                <p class="text-sm text-zinc-500">{{ __('No notifications yet.') }}</p>
            @else
                <div class="max-h-72 space-y-1 overflow-y-auto">
                    @foreach ($this->recent as $notification)
                        <button
                            type="button"
                            wire:key="notification-preview-{{ $notification->id }}"
                            wire:click="openNotification('{{ $notification->id }}')"
                            class="flex w-full items-start gap-2 rounded-md p-2 text-left text-sm hover:bg-zinc-50"
                        >
                            <span class="mt-1.5 size-2 flex-shrink-0 rounded-full bg-brand-primary"></span>
                            <span class="flex-1">
                                <span class="block font-medium">{{ $notification->data['subject'] ?? $notification->data['message'] ?? '' }}</span>
                                <span class="block truncate text-zinc-500">{{ $notification->data['message'] ?? '' }}</span>
                                <span class="mt-0.5 block text-xs text-zinc-400">{{ $notification->created_at?->diffForHumans() }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>

                <div class="mt-3 border-t border-zinc-200 pt-3">
                    <a href="{{ route('account.notifications') }}" wire:navigate class="text-sm font-medium text-brand-primary hover:underline">
                        {{ __('View all') }}
                    </a>
                </div>
            @endif
        </div>
    @endif
</div>
