<div>
    @if ($this->announcement)
        <x-modal :show="true" onClose="dismiss" wire:key="announcement-popup-{{ $this->announcement->id }}">
            <button
                type="button"
                x-on:click="open = false"
                aria-label="{{ __('Dismiss') }}"
                class="absolute right-4 top-4 text-zinc-400 transition-colors hover:text-zinc-600"
            >
                <x-app-icon name="x-circle" class="size-6" />
            </button>

            <h2 class="text-lg font-semibold">{{ $this->announcement->title }}</h2>
            <p class="mt-2 text-sm text-zinc-600">{{ $this->announcement->body }}</p>
        </x-modal>
    @endif
</div>
