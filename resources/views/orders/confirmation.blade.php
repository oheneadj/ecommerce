<x-layouts::storefront :title="__('Order confirmed')" :noindex="true">
    <livewire:storefront.order-confirmation-page :order-ulid="$order" />
</x-layouts::storefront>
