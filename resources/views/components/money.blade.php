@props(['amount', 'currency' => null])

@php
    $currency ??= config('app.currency', 'GHS');
    $symbol = match ($currency) {
        'GHS' => 'GH₵',
        default => $currency.' ',
    };
@endphp

{{ $symbol.number_format(($amount ?? 0) / 100, 2) }}
