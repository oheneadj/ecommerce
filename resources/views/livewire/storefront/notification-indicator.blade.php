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
                <div class="max-h-72 space-y-3 overflow-y-auto">
                    @foreach ($this->recent as $notification)
                        <div wire:key="notification-preview-{{ $notification->id }}" class="text-sm">
                            <p class="font-medium">{{ $notification->data['subject'] ?? '' }}</p>
                            <p class="truncate text-zinc-500">{{ $notification->data['message'] ?? '' }}</p>
                            <p class="mt-0.5 text-xs text-zinc-400">{{ $notification->created_at?->diffForHumans() }}</p>
                        </div>
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
