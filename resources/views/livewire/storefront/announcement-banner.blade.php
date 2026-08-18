<div>
    @if ($this->announcement)
        <div class="bg-brand-primary px-4 py-3 text-white sm:px-6" wire:key="announcement-{{ $this->announcement->id }}">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4">
                <div class="text-sm">
                    <span class="font-medium">{{ $this->announcement->title }}</span>
                    <span class="ms-1 opacity-90">{{ $this->announcement->body }}</span>
                </div>

                <button
                    type="button"
                    wire:click="dismiss"
                    aria-label="{{ __('Dismiss') }}"
                    class="shrink-0 text-white/80 transition-colors hover:text-white"
                >
                    <x-app-icon name="x-circle" class="size-5" />
                </button>
            </div>
        </div>
    @endif
</div>
