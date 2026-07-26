<x-filament-widgets::widget>
    <x-filament::section heading="Top Products (this month)">
        @if ($products->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No sales recorded yet this month.</p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($products as $product)
                    <li class="flex items-center justify-between py-2">
                        <span>{{ $product['product_name'] }}</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $product['quantity_sold'] }} sold</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
