<?php

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsApiRequests;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase, SignsApiRequests;

    public function test_retrying_with_the_same_key_replays_the_original_response(): void
    {
        $key = ApiKey::factory()->create();
        $headers = ['Idempotency-Key' => 'order-1001'];

        $first = $this->api($key, 'POST', '/api/v1/payments', $this->paymentPayload(), $headers)->assertCreated();
        $second = $this->api($key, 'POST', '/api/v1/payments', $this->paymentPayload(), $headers)->assertCreated();

        $second->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame($first->json(), $second->json());
        $this->assertSame(1, Payment::count());
    }

    public function test_reusing_a_key_with_a_different_body_is_rejected(): void
    {
        $key = ApiKey::factory()->create();
        $headers = ['Idempotency-Key' => 'order-1002'];

        $this->api($key, 'POST', '/api/v1/payments', $this->paymentPayload(), $headers)->assertCreated();

        $this->api($key, 'POST', '/api/v1/payments', $this->paymentPayload(['amount' => 9999]), $headers)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'idempotency_key_reused');

        $this->assertSame(1, Payment::count());
    }

    public function test_reusing_a_key_on_a_different_endpoint_is_rejected(): void
    {
        $key = ApiKey::factory()->create();
        $payment = Payment::factory()->requiresCapture()->for($key->merchant)->create();
        $headers = ['Idempotency-Key' => 'shared-key'];

        $this->api($key, 'POST', "/api/v1/payments/{$payment->id}/capture", [], $headers)->assertOk();

        $this->api($key, 'POST', "/api/v1/payments/{$payment->id}/cancel", [], $headers)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'idempotency_key_reused');
    }

    public function test_keys_are_scoped_per_merchant(): void
    {
        $keyA = ApiKey::factory()->create();
        $keyB = ApiKey::factory()->create();
        $headers = ['Idempotency-Key' => 'same-key'];

        $a = $this->api($keyA, 'POST', '/api/v1/payments', $this->paymentPayload(), $headers)->assertCreated();
        $b = $this->api($keyB, 'POST', '/api/v1/payments', $this->paymentPayload(), $headers)->assertCreated();

        $b->assertHeaderMissing('Idempotent-Replayed');
        $this->assertNotSame($a->json('id'), $b->json('id'));
        $this->assertSame(2, Payment::count());
    }

    public function test_post_without_a_key_is_rejected(): void
    {
        $key = ApiKey::factory()->create();

        $this->api($key, 'POST', '/api/v1/payments', $this->paymentPayload(), ['Idempotency-Key' => null])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'idempotency_key_required');

        $this->assertSame(0, Payment::count());
    }

    public function test_declined_payments_are_replayed_too(): void
    {
        $key = ApiKey::factory()->create();
        $payload = $this->paymentPayload(['card' => ['number' => '4000000000000002']]);
        $headers = ['Idempotency-Key' => 'declined-1'];

        $this->api($key, 'POST', '/api/v1/payments', $payload, $headers)->assertStatus(402);
        $this->api($key, 'POST', '/api/v1/payments', $payload, $headers)
            ->assertStatus(402)
            ->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame(1, Payment::count());
    }

    public function test_refund_retries_do_not_refund_twice(): void
    {
        $key = ApiKey::factory()->create();
        $payment = Payment::factory()->for($key->merchant)->create(['amount' => 1000, 'amount_captured' => 1000]);
        $headers = ['Idempotency-Key' => 'refund-1'];

        $this->api($key, 'POST', "/api/v1/payments/{$payment->id}/refunds", ['amount' => 400], $headers)->assertCreated();
        $this->api($key, 'POST', "/api/v1/payments/{$payment->id}/refunds", ['amount' => 400], $headers)->assertCreated();

        $this->assertSame(400, $payment->fresh()->amount_refunded);
        $this->assertSame(1, $payment->refunds()->count());
    }

    public function test_get_requests_do_not_need_a_key(): void
    {
        $key = ApiKey::factory()->create();

        $this->api($key, 'GET', '/api/v1/payments')->assertOk();
    }
}
