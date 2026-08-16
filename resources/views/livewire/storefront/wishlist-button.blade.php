<button
    type="button"
    wire:click="toggle"
    wire:loading.attr="disabled"
    wire:target="toggle"
    aria-label="{{ $this->isWishlisted ? __('Remove from wishlist') : __('Add to wishlist') }}"
    class="flex size-8 items-center justify-center rounded-full bg-white/90 text-zinc-500 shadow-sm backdrop-blur transition-colors hover:text-red-500"
>
    <x-app-icon name="heart" :filled="$this->isWishlisted" class="size-4 {{ $this->isWishlisted ? 'text-red-500' : '' }}" />
</button>
