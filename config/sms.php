<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default SMS Driver
    |--------------------------------------------------------------------------
    | Supported drivers: "log", "twilio"
    |
    | In development, "log" writes the SMS message to storage/logs/laravel.log.
    | In production, you can set SMS_DRIVER=twilio with your credentials.
    */
    'default' => env('SMS_DRIVER', 'log'),

    'drivers' => [
        'log' => [
            'channel' => env('LOG_CHANNEL', 'stack'),
        ],

        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
            'from' => env('TWILIO_NUMBER'),
        ],
    ],

];
