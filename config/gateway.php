<?php

use App\Services\Processor\SimulatedProcessor;

$simulatorEnabled = (bool) env('GATEWAY_SIMULATOR_ENABLED', in_array(env('APP_ENV'), ['local', 'testing'], true));

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
    | Providers
    |--------------------------------------------------------------------------
    |
    | Each upstream bank or PSP has its own API and gets its own driver class
    | implementing App\Services\Processor\PaymentProcessor. Providers stored
    | in the database name one of these keys as their driver.
    |
    | The simulated driver approves payments without moving money, so it is
    | only registered while the simulator is enabled (local and testing by
    | default). Never enable it in production. Providers whose driver is not
    | registered cannot be assigned or charged. Its callbacks are rejected
    | unless a dedicated callback secret is set.
    |
    | Payments waiting on the customer (requires_action) are canceled after
    | pending_payment_ttl_minutes by the scheduled payments:expire-pending.
    |
    */

    'provider_drivers' => array_filter([
        'simulated' => $simulatorEnabled ? SimulatedProcessor::class : null,
    ]),

    'simulator_enabled' => $simulatorEnabled,

    'simulator_callback_secret' => env('GATEWAY_SIMULATOR_CALLBACK_SECRET'),

    'pending_payment_ttl_minutes' => (int) env('GATEWAY_PENDING_PAYMENT_TTL_MINUTES', 30),

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
