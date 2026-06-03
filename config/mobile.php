<?php

return [
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
