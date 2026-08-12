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
    | Active Provider
    |--------------------------------------------------------------------------
    |
    | Once a Super Admin has saved Store Settings, App\Payments\PaymentManager
    | reads StoreSetting::current()->active_payment_provider instead —
    | 'default' above is only the fallback for a fresh deployment before that
    | first save. Both Paystack and Moolre accept card payments today, so
    | there's no per-channel routing — one active provider handles every
    | checkout until an admin switches it.
    |
    */

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
