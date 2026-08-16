<div class="space-y-6">
    <h1 class="text-2xl font-semibold">{{ __('Notifications') }}</h1>

    <x-card>
        @if ($this->notifications->isEmpty())
            <p class="text-sm text-zinc-500">{{ __("You don't have any notifications yet.") }}</p>
        @else
            <div class="divide-y divide-zinc-200">
                @foreach ($this->notifications as $notification)
                    <div wire:key="notification-{{ $notification->id }}" class="py-3">
                        <p class="font-medium">{{ $notification->data['subject'] ?? '' }}</p>
                        <p class="text-sm text-zinc-600">{{ $notification->data['message'] ?? '' }}</p>
                        <p class="mt-1 text-xs text-zinc-400">{{ $notification->created_at?->format('d M Y, H:i') }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $this->notifications->links() }}
            </div>
        @endif
    </x-card>
</div>
