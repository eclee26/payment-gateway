<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Request Signing
    |--------------------------------------------------------------------------
    |
    | Merchants sign every API request with HMAC-SHA256. Requests whose
    | timestamp is further than this many seconds from server time are
    | rejected, and each nonce may only be used once within the TTL.
    |
    */

    'signature_tolerance' => (int) env('GATEWAY_SIGNATURE_TOLERANCE', 300),

    'nonce_ttl' => (int) env('GATEWAY_NONCE_TTL', 600),

    /*
    |--------------------------------------------------------------------------
    | Rate Limits (requests per minute)
    |--------------------------------------------------------------------------
    */

    'rate_limit' => (int) env('GATEWAY_RATE_LIMIT', 100),

    'auth_failure_limit' => (int) env('GATEWAY_AUTH_FAILURE_LIMIT', 20),

    /*
    |--------------------------------------------------------------------------
    | Idempotency
    |--------------------------------------------------------------------------
    |
    | Stored idempotency keys are pruned after this many hours.
    |
    */

    'idempotency_ttl_hours' => (int) env('GATEWAY_IDEMPOTENCY_TTL_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    */

    'currencies' => ['USD', 'EUR', 'GBP', 'PHP', 'JPY', 'AUD', 'CAD', 'SGD'],

    'min_amount' => 1,

    'max_amount' => 99_999_999,

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | Delays (seconds) between delivery retries, and the HTTP timeout. Plain
    | http:// webhook URLs are only accepted when allow_insecure is true, and
    | private/loopback addresses only when allow_private_network is true.
    | Never enable allow_private_network in production (SSRF).
    |
    */

    'webhook_backoff' => [10, 60, 300, 1800, 7200],

    'webhook_timeout' => 10,

    'webhook_allow_insecure' => (bool) env('GATEWAY_WEBHOOK_ALLOW_INSECURE', env('APP_ENV') === 'local'),

    'webhook_allow_private_network' => (bool) env('GATEWAY_WEBHOOK_ALLOW_PRIVATE_NETWORK', false),

];
