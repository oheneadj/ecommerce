<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Payment Provider
    |--------------------------------------------------------------------------
    |
    | The provider key resolved by App\Payments\PaymentManager when no
    | specific channel dictates otherwise. Swapping or adding a provider is
    | a new driver class + an entry here — never an Action change.
    |
    */

    'default' => env('PAYMENT_PROVIDER', 'paystack'),

    /*
    |--------------------------------------------------------------------------
    | Channel → Provider Mapping
    |--------------------------------------------------------------------------
    |
    | Both providers can be active at once: Paystack handles card payments,
    | Moolre handles mobile money. The customer's chosen channel picks the
    | driver, not a single global default.
    |
    */

    'channels' => [
        'mobile_money' => 'moolre',
        'card' => 'paystack',
    ],

    'providers' => [
        'moolre' => [
            'api_key' => env('MOOLRE_API_KEY'),
            'webhook_secret' => env('MOOLRE_WEBHOOK_SECRET'),
        ],
        'paystack' => [
            'secret_key' => env('PAYSTACK_SECRET_KEY'),
            'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        ],
    ],

];
