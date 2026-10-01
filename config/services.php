<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'payments' => [
        // Which gateway students pay through: paystack (remita, flutterwave,
        // interswitch to follow). With demo=true every payment succeeds
        // instantly without contacting a gateway — for local development only.
        'default' => env('PAYMENT_GATEWAY', 'paystack'),
        'demo' => env('PAYMENT_DEMO_MODE', true),
    ],

    'paystack' => [
        'secret' => env('PAYSTACK_SECRET_KEY'),
        'public' => env('PAYSTACK_PUBLIC_KEY'),
    ],

    'remita' => [
        'merchant_id' => env('REMITA_MERCHANT_ID'),
        'service_type_id' => env('REMITA_SERVICE_TYPE_ID'),
        'api_key' => env('REMITA_API_KEY'),
        // Demo: https://remitademo.net/remita/exapp/api/v1/send/api
        'base_url' => env('REMITA_BASE_URL', 'https://login.remita.net/remita/exapp/api/v1/send/api'),
        // Demo: https://remitademo.net/remita
        'base_url_root' => env('REMITA_BASE_URL_ROOT', 'https://login.remita.net/remita'),
    ],

    'flutterwave' => [
        'secret' => env('FLUTTERWAVE_SECRET_KEY'),
        // The value you set as "secret hash" in the Flutterwave dashboard;
        // sent back verbatim in the verif-hash webhook header.
        'webhook_hash' => env('FLUTTERWAVE_WEBHOOK_HASH'),
    ],

];
