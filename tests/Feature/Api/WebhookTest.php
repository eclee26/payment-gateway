<?php

namespace Tests\Feature\Api;

use App\Jobs\SendWebhook;
use App\Models\ApiKey;
use App\Models\WebhookDelivery;
use App\Support\SafeUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\SignsApiRequests;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase, SignsApiRequests;

    /**
     * A public IP literal, so tests do not depend on DNS.
     */
    private const PUBLIC_URL = 'https://93.184.215.14/webhooks';

    public function test_successful_payment_sends_a_signed_webhook(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        $key = ApiKey::factory()->create();
        $key->merchant->update(['webhook_url' => self::PUBLIC_URL]);
        $secret = $key->merchant->webhook_secret;

        $paymentId = $this->api($key, 'POST', '/api/v1/payments', $this->paymentPayload())->assertCreated()->json('id');

        Http::assertSent(function (Request $request) use ($secret, $paymentId) {
            preg_match('/^t=(\d+),v1=([a-f0-9]{64})$/', $request->header('X-Gateway-Signature')[0], $m);
            $expected = hash_hmac('sha256', $m[1].'.'.$request->body(), $secret);

            return $request->url() === self::PUBLIC_URL
                && hash_equals($expected, $m[2])
                && $request['type'] === 'payment.succeeded'
                && $request['data']['object']['id'] === $paymentId
                && ! str_contains($request->body(), '4242424242424242');
        });

        $this->assertNotNull(WebhookDelivery::sole()->delivered_at);
    }

    public function test_no_webhook_is_sent_without_a_configured_url(): void
    {
        Http::fake();
        $key = ApiKey::factory()->create();

        $this->api($key, 'POST', '/api/v1/payments', $this->paymentPayload())->assertCreated();

        Http::assertNothingSent();
        $this->assertDatabaseCount('webhook_deliveries', 0);
    }

    public function test_rejected_request_does_not_send_a_webhook(): void
    {
        Http::fake();
        $key = ApiKey::factory()->create();
        $key->merchant->update(['webhook_url' => self::PUBLIC_URL]);

        $this->api($key, 'POST', '/api/v1/payments', $this->paymentPayload(['amount' => 0]))->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_url_that_became_unsafe_is_blocked_at_send_time(): void
    {
        Http::fake();
        $key = ApiKey::factory()->create();
        // Bypass save-time validation, simulating DNS that now points inward.
        $key->merchant->forceFill(['webhook_url' => 'https://127.0.0.1/hook'])->save();

        $this->api($key, 'POST', '/api/v1/payments', $this->paymentPayload())->assertCreated();

        Http::assertNothingSent();
        $this->assertSame('Webhook URL is not allowed.', WebhookDelivery::sole()->last_error);
    }

    public function test_non_2xx_response_is_recorded_and_retried(): void
    {
        Http::fake(['*' => Http::response('nope', 503)]);
        $key = ApiKey::factory()->create();
        $key->merchant->update(['webhook_url' => self::PUBLIC_URL]);
        $delivery = $key->merchant->webhookDeliveries()->create(['event_type' => 'payment.succeeded', 'payload' => ['id' => 'evt_test']]);

        try {
            (new SendWebhook($delivery))->handle();
            $this->fail('Expected the job to throw so the queue retries it.');
        } catch (RuntimeException) {
            // Thrown so the queue schedules a retry with backoff.
        }

        $delivery->refresh();
        $this->assertSame(503, $delivery->last_response_status);
        $this->assertNull($delivery->delivered_at);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame([10, 60, 300, 1800, 7200], (new SendWebhook($delivery))->backoff());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeUrls(): array
    {
        return [
            'loopback' => ['https://127.0.0.1/hook'],
            'localhost name' => ['https://localhost/hook'],
            'private 10/8' => ['https://10.0.0.5/hook'],
            'private 192.168/16' => ['https://192.168.1.10/hook'],
            'private 172.16/12' => ['https://172.16.0.1/hook'],
            'cloud metadata' => ['https://169.254.169.254/latest/meta-data'],
            'carrier-grade NAT' => ['https://100.64.0.1/hook'],
            'unspecified' => ['https://0.0.0.0/hook'],
            'ipv6 loopback' => ['https://[::1]/hook'],
            'credentials in url' => ['https://user:pass@93.184.215.14/hook'],
            'plain http' => ['http://93.184.215.14/hook'],
            'non-http scheme' => ['ftp://93.184.215.14/hook'],
            'garbage' => ['not a url'],
        ];
    }

    #[DataProvider('unsafeUrls')]
    public function test_unsafe_webhook_urls_are_rejected(string $url): void
    {
        $this->assertNull(SafeUrl::resolve($url));
    }

    public function test_public_https_url_is_allowed_and_pinned_to_its_ip(): void
    {
        $this->assertSame(
            ['host' => '93.184.215.14', 'port' => 443, 'ip' => '93.184.215.14'],
            SafeUrl::resolve(self::PUBLIC_URL),
        );
    }
}
