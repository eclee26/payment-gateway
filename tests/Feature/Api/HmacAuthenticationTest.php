<?php

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SignsApiRequests;
use Tests\TestCase;

class HmacAuthenticationTest extends TestCase
{
    use RefreshDatabase, SignsApiRequests;

    public function test_correctly_signed_request_is_accepted(): void
    {
        $key = ApiKey::factory()->create();

        $this->api($key, 'GET', '/api/v1/payments')->assertOk();
    }

    public function test_signature_matches_the_documented_scheme(): void
    {
        $key = ApiKey::factory()->create();
        $body = json_encode($this->paymentPayload());
        $timestamp = (string) time();
        $nonce = 'nonce-0123456789abcdef';

        // Built by hand from the spec, independent of the production helper.
        $stringToSign = $timestamp."\n".$nonce."\nPOST\n/api/v1/payments\n".hash('sha256', $body);
        $signature = hash_hmac('sha256', $stringToSign, $key->secret);

        $this->call('POST', '/api/v1/payments', [], [], [], $this->transformHeadersToServerVars([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Api-Key' => $key->key_id,
            'X-Timestamp' => $timestamp,
            'X-Nonce' => $nonce,
            'X-Signature' => 'v1='.$signature,
            'Idempotency-Key' => 'spec-test',
        ]), $body)->assertCreated();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function requiredHeaders(): array
    {
        return [
            'api key' => ['X-Api-Key'],
            'timestamp' => ['X-Timestamp'],
            'nonce' => ['X-Nonce'],
            'signature' => ['X-Signature'],
        ];
    }

    #[DataProvider('requiredHeaders')]
    public function test_missing_auth_header_is_rejected(string $header): void
    {
        $key = ApiKey::factory()->create();

        $this->api($key, 'GET', '/api/v1/payments', headers: [$header => null])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'authentication_failed');
    }

    public function test_wrong_secret_is_rejected(): void
    {
        $key = ApiKey::factory()->create();

        $this->api($key, 'GET', '/api/v1/payments', overrides: ['secret' => 'sk_wrong'])->assertUnauthorized();
    }

    public function test_tampered_body_is_rejected(): void
    {
        $key = ApiKey::factory()->create();
        $signed = json_encode($this->paymentPayload(['amount' => 100]));

        $this->api($key, 'POST', '/api/v1/payments', $this->paymentPayload(['amount' => 999_999]), overrides: ['signed_body' => $signed])
            ->assertUnauthorized();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_tampered_path_is_rejected(): void
    {
        $key = ApiKey::factory()->create();

        $this->api($key, 'GET', '/api/v1/payments?limit=5', overrides: ['signed_uri' => '/api/v1/payments'])
            ->assertUnauthorized();
    }

    public function test_malformed_signature_header_is_rejected(): void
    {
        $key = ApiKey::factory()->create();

        $this->api($key, 'GET', '/api/v1/payments', headers: ['X-Signature' => 'sha256=abc'])->assertUnauthorized();
    }

    public function test_stale_timestamp_is_rejected(): void
    {
        $key = ApiKey::factory()->create();

        $this->api($key, 'GET', '/api/v1/payments', overrides: ['timestamp' => time() - 301])->assertUnauthorized();
    }

    public function test_future_timestamp_is_rejected(): void
    {
        $key = ApiKey::factory()->create();

        $this->api($key, 'GET', '/api/v1/payments', overrides: ['timestamp' => time() + 301])->assertUnauthorized();
    }

    public function test_replayed_nonce_is_rejected(): void
    {
        $key = ApiKey::factory()->create();
        $nonce = 'replay-nonce-0123456789';

        $this->api($key, 'GET', '/api/v1/payments', overrides: ['nonce' => $nonce])->assertOk();
        $this->api($key, 'GET', '/api/v1/payments', overrides: ['nonce' => $nonce])->assertUnauthorized();
    }

    public function test_revoked_key_is_rejected(): void
    {
        $key = ApiKey::factory()->revoked()->create();

        $this->api($key, 'GET', '/api/v1/payments')->assertUnauthorized();
    }

    public function test_unknown_key_gets_the_same_generic_error_as_a_bad_signature(): void
    {
        $key = ApiKey::factory()->create();
        $unknown = ApiKey::factory()->make(['key_id' => 'key_doesnotexist']);

        $unknownResponse = $this->api($unknown, 'GET', '/api/v1/payments')->assertUnauthorized();
        $badSigResponse = $this->api($key, 'GET', '/api/v1/payments', overrides: ['secret' => 'sk_wrong'])->assertUnauthorized();

        $this->assertSame($unknownResponse->json(), $badSigResponse->json());
    }

    public function test_repeated_failures_from_one_ip_are_throttled(): void
    {
        config(['gateway.auth_failure_limit' => 3]);
        $key = ApiKey::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->api($key, 'GET', '/api/v1/payments', overrides: ['secret' => 'sk_wrong'])->assertUnauthorized();
        }

        // Even a correctly signed request is blocked once the IP is throttled.
        $this->api($key, 'GET', '/api/v1/payments')
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'too_many_failed_attempts');
    }

    public function test_api_responses_are_not_cacheable_and_carry_security_headers(): void
    {
        $key = ApiKey::factory()->create();

        $this->api($key, 'GET', '/api/v1/payments')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }
}
