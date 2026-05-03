<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fonnte Configuration
    |--------------------------------------------------------------------------
    */
    'fonnte_token'      => env('FONNTE_TOKEN'),
    'fonnte_send_url'   => env('FONNTE_SEND_URL', 'https://api.fonnte.com/send'),
    'fonnte_bot_number' => env('FONNTE_BOT_NUMBER', '081247947246'),

    /*
    |--------------------------------------------------------------------------
    | Magic Login Configuration
    |--------------------------------------------------------------------------
    */
    'magic_link_ttl_minutes' => (int) env('MAGIC_LINK_TTL_MINUTES', 5),

    /*
    |--------------------------------------------------------------------------
    | Internal API Key
    |--------------------------------------------------------------------------
    | Used by internal applications to validate magic login tokens.
    | Generate with: php -r "echo bin2hex(random_bytes(32)).PHP_EOL;"
    */
    'internal_api_key' => env('INTERNAL_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limit
    |--------------------------------------------------------------------------
    */
    'rate_limit_max_tokens'   => 5,
    'rate_limit_window_minutes' => 10,

    /*
    |--------------------------------------------------------------------------
    | Application Base URLs (from env)
    |--------------------------------------------------------------------------
    */
    'app_base_urls' => [
        'absensi'      => env('APP_BASE_URL_ABSENSI', 'https://absensi.pta-papuabarat.go.id'),
        'aplikasicuti' => env('APP_BASE_URL_APLIKASICUTI', 'https://aplikasicuti.pta-papuabarat.go.id'),
        'bakusapa'     => env('APP_BASE_URL_BAKUSAPA', 'https://bakusapa.pta-papuabarat.go.id'),
        'bukutamu'     => env('APP_BASE_URL_BUKUTAMU', 'https://bukutamu.pta-papuabarat.go.id'),
        'koperasi'     => env('APP_BASE_URL_KOPERASI', 'https://koperasi.pta-papuabarat.go.id'),
        'sikasuar'     => env('APP_BASE_URL_SIKASUAR', 'https://sikasuar.pta-papuabarat.go.id'),
        'simisol'      => env('APP_BASE_URL_SIMISOL', 'https://simisol.pta-papuabarat.go.id'),
        'siperlatin'   => env('APP_BASE_URL_SIPERLATIN', 'https://siperlatin.pta-papuabarat.go.id'),
        'smart'        => env('APP_BASE_URL_SMART', 'https://smart.pta-papuabarat.go.id'),
        'survey'       => env('APP_BASE_URL_SURVEY', 'https://survey.pta-papuabarat.go.id'),
        'tes'          => env('APP_BASE_URL_TES', 'https://tes.pta-papuabarat.go.id'),
        'wfh'          => env('APP_BASE_URL_WFH', 'https://wfh.pta-papuabarat.go.id'),
    ],
];
