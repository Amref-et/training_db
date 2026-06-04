<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mobile App Download
    |--------------------------------------------------------------------------
    */

    'app_name' => env('MOBILE_APP_NAME', 'Amref training DB'),

    'apk_public_path' => env('MOBILE_APK_PUBLIC_PATH', 'mobile/amref-training-db.apk'),

    /*
    |--------------------------------------------------------------------------
    | Mobile API Token Lifetime
    |--------------------------------------------------------------------------
    |
    | Mobile clients authenticate with Sanctum personal access tokens. Set this
    | value to 0 if tokens should not receive an explicit expiration timestamp.
    |
    */

    'token_expiration_days' => (int) env('MOBILE_TOKEN_EXPIRATION_DAYS', 30),
];
