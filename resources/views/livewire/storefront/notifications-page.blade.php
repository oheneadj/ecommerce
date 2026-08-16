<div class="space-y-6">
    <h1 class="text-2xl font-semibold">{{ __('Notifications') }}</h1>

    <x-card>
        @if ($this->notifications->isEmpty())
            <p class="text-sm text-zinc-500">{{ __("You don't have any notifications yet.") }}</p>
        @else
            <div class="divide-y divide-zinc-200">
                @foreach ($this->notifications as $notification)
                    <button
                        type="button"
                        wire:key="notification-{{ $notification->id }}"
                        wire:click="openNotification('{{ $notification->id }}')"
                        class="flex w-full items-start gap-3 py-3 text-left {{ $notification->read_at === null ? 'bg-brand-primary/5' : '' }}"
                    >
                        <span class="mt-1.5 size-2 flex-shrink-0 rounded-full {{ $notification->read_at === null ? 'bg-brand-primary' : 'bg-transparent' }}"></span>
                        <span class="flex-1">
                            <span class="block font-medium {{ $notification->read_at === null ? 'text-zinc-900' : 'text-zinc-600' }}">{{ $notification->data['subject'] ?? $notification->data['message'] ?? '' }}</span>
                            @if ($expandedNotificationId === $notification->id)
                                <span class="mt-1 block text-sm text-zinc-600">{{ $notification->data['message'] ?? '' }}</span>
                            @endif
                            <span class="mt-1 block text-xs text-zinc-400">{{ $notification->created_at?->format('d M Y, H:i') }}</span>
                        </span>
                    </button>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $this->notifications->links() }}
            </div>
        @endif
    </x-card>
</div>
