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

    /*
    |--------------------------------------------------------------------------
    | Active Provider
    |--------------------------------------------------------------------------
    |
    | Once a Super Admin has saved Store Settings, App\Sms\SmsManager reads
    | StoreSetting::current()->active_sms_provider instead — 'default' above
    | is only the fallback for a fresh deployment before that first save.
    |
    */

    'providers' => [
        'moolre' => [
            'api_key' => env('MOOLRE_API_KEY'),
            'sender_id' => env('MOOLRE_SENDER_ID'),
        ],
        'giantsms' => [
            'api_token' => env('GIANTSMS_TOKEN'),
            'sender_id' => env('GIANTSMS_SENDER_ID'),
        ],
    ],

];
