<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">{{ __('My Addresses') }}</h1>
        <x-button wire:click="startCreate" variant="primary">{{ __('Add address') }}</x-button>
    </div>

    @if ($this->addresses->isEmpty())
        <x-card>
            <p class="text-sm text-zinc-500">{{ __("You haven't saved any addresses yet.") }}</p>
        </x-card>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($this->addresses as $address)
                <x-card wire:key="address-{{ $address->id }}">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-medium">
                                {{ $address->label ?: $address->recipient_name }}
                                @if ($address->is_default)
                                    <x-status-badge color="success">{{ __('Default') }}</x-status-badge>
                                @endif
                            </p>
                            <p class="mt-1 text-sm text-zinc-600">{{ $address->recipient_name }}</p>
                            <p class="text-sm text-zinc-600">{{ $address->phone }}</p>
                            <p class="text-sm text-zinc-600">
                                {{ $address->line1 }}@if ($address->line2), {{ $address->line2 }}@endif<br>
                                {{ $address->city }}@if ($address->region), {{ $address->region }}@endif
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-button wire:click="startEdit({{ $address->id }})" variant="outline">{{ __('Edit') }}</x-button>

                        @unless ($address->is_default)
                            <x-button wire:click="setDefault({{ $address->id }})" variant="ghost">{{ __('Set as default') }}</x-button>
                        @endunless

                        <x-button
                            wire:click="delete({{ $address->id }})"
                            wire:confirm="{{ __('Remove this address?') }}"
                            variant="danger"
                        >{{ __('Remove') }}</x-button>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif

    <x-modal wire-model="showForm" name="address-form">
        <form wire:submit="save" class="space-y-4">
            <h2 class="text-lg font-medium">{{ $editingAddressId ? __('Edit address') : __('Add address') }}</h2>

            <x-input wire:model="label" label="{{ __('Label') }}" placeholder="{{ __('e.g. Home, Office') }}" />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-input wire:model="recipient_name" label="{{ __('Recipient name') }}" placeholder="{{ __('e.g. Ama Owusu') }}" required />
                <x-input wire:model="phone" label="{{ __('Phone') }}" placeholder="{{ __('+233201234567 or 0201234567') }}" required />
            </div>

            <x-input wire:model="line1" label="{{ __('Address line 1') }}" placeholder="{{ __('Street address, building name') }}" required />
            <x-input wire:model="line2" label="{{ __('Address line 2') }}" placeholder="{{ __('Apartment, suite, unit (optional)') }}" />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-input wire:model="city" label="{{ __('City') }}" placeholder="{{ __('e.g. Accra') }}" required />
                <x-input wire:model="region" label="{{ __('Region') }}" placeholder="{{ __('e.g. Greater Accra') }}" />
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="is_default" class="rounded border-zinc-300">
                {{ __('Set as default address') }}
            </label>

            <div class="flex justify-end gap-2">
                <x-button type="button" wire:click="cancel" variant="ghost">{{ __('Cancel') }}</x-button>
                <x-button type="submit" variant="primary">{{ __('Save address') }}</x-button>
            </div>
        </form>
    </x-modal>
</div>
