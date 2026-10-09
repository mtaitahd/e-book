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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Abliner Payments
    |--------------------------------------------------------------------------
    |
    | Secrets are read from the server environment ONLY. They are never stored
    | as the FALLBACK for any field the admin has not saved. The admin Payment
    | Settings page (/admin/settings/payments) can set, rotate and clear them;
    | those are stored ENCRYPTED with the application key, and a saved value
    | wins over the value here. See App\Settings\PaymentProviderConfig, which is
    | the only place the application should read these from.
    |
    | base_url           Abliner API host. /api/v1 is appended by the service.
    | api_key            Secret. Bearer token for outbound API calls (tsl_live_...).
    | webhook_secret     Secret (whsec_...). Signs outbound deposit requests AND
    |                    verifies inbound webhook signatures. Abliner refuses
    |                    POST /deposits with webhook_secret_required when it is
    |                    missing, so this is effectively mandatory.
    | webhook_url        Public HTTPS URL Abliner posts callbacks to. Defaults
    |                    to the app's own /webhooks/abliner route.
    | enabled            Operator kill switch for new collections.
    | verify_on_webhook  Re-fetch the transaction from the API before trusting
    |                    an inbound completed callback.
    |
    */
    'abliner' => [
        'base_url' => env('ABLINER_BASE_URL', 'https://abliner.net'),
        'api_key' => env('ABLINER_API_KEY'),
        'webhook_secret' => env('ABLINER_WEBHOOK_SECRET'),
        'webhook_url' => env('ABLINER_WEBHOOK_URL'),
        'enabled' => env('ABLINER_ENABLED', true),
        'verify_on_webhook' => env('ABLINER_VERIFY_ON_WEBHOOK', true),
    ],

];
