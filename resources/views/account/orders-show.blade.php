<x-layouts::storefront :title="__('Order detail')">
    <x-account.layout>
        <livewire:storefront.order-detail-page :order-ulid="$order" />
    </x-account.layout>
</x-layouts::storefront>
