<div>
    @if ($this->announcement)
        <div class="bg-brand-primary px-4 py-3 text-center text-white sm:px-6" wire:key="announcement-{{ $this->announcement->id }}">
            <div class="mx-auto max-w-6xl text-sm">
                <span class="font-medium">{{ $this->announcement->title }}</span>
                <span class="ms-1 opacity-90">{{ $this->announcement->body }}</span>
            </div>
        </div>
    @endif
</div>
