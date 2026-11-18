<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Bitrix24 Integration
    |--------------------------------------------------------------------------
    |
    | Settings for the Bitrix24 Local Application. The portal is the domain
    | without the scheme (e.g. "company.bitrix24.com"). Secrets live in .env
    | only and must never be committed.
    |
    */

    'portal' => env('BITRIX24_PORTAL'),

    'client_id' => env('BITRIX24_CLIENT_ID'),

    'client_secret' => env('BITRIX24_CLIENT_SECRET'),

    'oauth_server' => env('BITRIX24_OAUTH_SERVER', 'https://oauth.bitrix.info'),

    // The SPA page that finishes a sign-in. Not /auth/bitrix/callback: when the SPA and the API share
    // one address (needed for the app to work inside Bitrix24), that path is Laravel's.
    'frontend_callback_url' => env(
        'BITRIX24_FRONTEND_CALLBACK_URL',
        env('FRONTEND_URL', 'http://localhost:5173').'/auth/bitrix/done'
    ),

    // The frontend page the app opens on inside Bitrix24 (it signs in through Bitrix's JS SDK).
    'frontend_app_url' => env(
        'BITRIX24_FRONTEND_APP_URL',
        env('FRONTEND_URL', 'http://localhost:5173').'/auth/bitrix/app'
    ),

    'timeout' => (int) env('BITRIX24_TIMEOUT', 30),

    // Public base URL Bitrix uses to reach us (the ngrok URL locally).
    'public_url' => env('BITRIX24_PUBLIC_URL', env('APP_URL')),

];
