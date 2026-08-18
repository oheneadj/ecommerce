<div>
    @if ($this->shouldShow)
        <x-callout variant="warning" icon="x-circle" class="mb-6">
            {{ __('Your email address is unverified.') }}
            <x-button variant="link-primary" wire:click="resend">
                {{ __('Click here to re-send the verification email.') }}
            </x-button>
        </x-callout>
    @endif
</div>
