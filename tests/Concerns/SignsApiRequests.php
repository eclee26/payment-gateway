<?php

namespace Tests\Concerns;

use App\Http\Middleware\VerifyHmacSignature;
use App\Models\ApiKey;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

trait SignsApiRequests
{
    /**
     * Send an HMAC-signed API request.
     *
     * $overrides lets tests break individual parts of the signature:
     * timestamp, nonce, secret, signed_body, signed_uri.
     * A header set to null in $headers is omitted.
     *
     * @param  array<string, mixed>|null  $data
     * @param  array<string, string|null>  $headers
     * @param  array<string, mixed>  $overrides
     */
    protected function api(ApiKey $key, string $method, string $uri, ?array $data = null, array $headers = [], array $overrides = []): TestResponse
    {
        $body = $data === null ? '' : json_encode($data);
        $timestamp = (string) ($overrides['timestamp'] ?? time());
        $nonce = $overrides['nonce'] ?? (string) Str::uuid();

        $signature = hash_hmac('sha256', VerifyHmacSignature::stringToSign(
            $timestamp,
            $nonce,
            $method,
            $overrides['signed_uri'] ?? $uri,
            $overrides['signed_body'] ?? $body,
        ), $overrides['secret'] ?? $key->secret);

        $headers = array_filter(array_merge([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Api-Key' => $key->key_id,
            'X-Timestamp' => $timestamp,
            'X-Nonce' => $nonce,
            'X-Signature' => 'v1='.$signature,
        ], $method === 'POST' ? ['Idempotency-Key' => (string) Str::uuid()] : [], $headers), fn ($value) => $value !== null);

        return $this->call($method, $uri, [], [], [], $this->transformHeadersToServerVars($headers), $body);
    }

    /**
     * A valid payment request body.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function paymentPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'amount' => 2500,
            'currency' => 'usd',
            'description' => 'Order #1001',
            'card' => [
                'number' => '4242424242424242',
                'exp_month' => 12,
                'exp_year' => now()->year + 2,
                'cvc' => '123',
            ],
        ], $overrides);
    }
}
