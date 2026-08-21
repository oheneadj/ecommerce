@props(['amount', 'currency' => null])

{{ \App\Support\CurrencySymbol::for($currency).number_format(($amount ?? 0) / 100, 2) }}
