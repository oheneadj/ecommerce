<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default SMS Provider
    |--------------------------------------------------------------------------
    |
    | The provider key resolved by App\Sms\SmsManager. Swapping or adding a
    | provider is a new driver class + an entry here — never an Action change.
    |
    */

    'default' => env('SMS_PROVIDER', 'moolre'),

    'providers' => [
        'moolre' => [
            'api_key' => env('MOOLRE_API_KEY'),
            'sender_id' => env('MOOLRE_SENDER_ID'),
        ],
    ],

];
