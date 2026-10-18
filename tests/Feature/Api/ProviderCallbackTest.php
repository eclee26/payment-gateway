<?php

namespace Tests\Feature\Api;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\ApiKey;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\Provider;
use App\Models\WebhookDelivery;
use App\Services\PaymentService;
use App\Services\Processor\SimulatedProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SignsApiRequests;
use Tests\TestCase;

class ProviderCallbackTest extends TestCase
{
    use RefreshDatabase, SignsApiRequests;

    private function pendingPayment(): Payment
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        $key = ApiKey::factory()->for(Merchant::factory()->withPaymentMethods([PaymentMethod::Gcash]))->create();
        $key->merchant->update(['webhook_url' => 'https://93.184.215.14/webhooks']);

        $id = $this->api($key, 'POST', '/api/v1/payments', ['payment_method' => 'GCASH', 'amount' => 5000, 'currency' => 'PHP'])
            ->assertCreated()
            ->json('id');

        return Payment::with('provider')->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sendCallback(Provider $provider, array $data, ?string $signature = null): TestResponse
    {
        $body = json_encode($data);

        return $this->call('POST', "/api/provider-callbacks/{$provider->code}", [], [], [], $this->transformHeadersToServerVars([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Simulator-Signature' => $signature ?? SimulatedProcessor::sign($body),
        ]), $body);
    }

    public function test_signed_success_callback_completes_the_payment(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        $payment = $this->pendingPayment();

        $this->sendCallback($payment->provider, ['reference' => $payment->provider_reference, 'status' => 'succeeded'])
            ->assertOk();

        $payment->refresh();
        $this->assertSame(PaymentStatus::Succeeded, $payment->status);
        $this->assertSame(5000, $payment->amount_captured);
        $this->assertNull($payment->next_action);
        $this->assertSame('payment.succeeded', WebhookDelivery::sole()->event_type);
    }

    public function test_failure_callback_fails_the_payment_and_clears_the_fee(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        $payment = $this->pendingPayment();
        $this->assertGreaterThan(0, $payment->fee_amount);

        $this->sendCallback($payment->provider, ['reference' => $payment->provider_reference, 'status' => 'failed', 'decline_code' => 'insufficient_balance'])
            ->assertOk();

        $payment->refresh();
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame('insufficient_balance', $payment->failure_code);
        $this->assertSame(0, $payment->fee_amount);
        $this->assertSame('payment.failed', WebhookDelivery::sole()->event_type);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $payment = $this->pendingPayment();

        $this->sendCallback($payment->provider, ['reference' => $payment->provider_reference, 'status' => 'succeeded'], str_repeat('0', 64))
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'invalid_callback');

        $this->assertSame(PaymentStatus::RequiresAction, $payment->fresh()->status);
    }

    public function test_duplicate_callbacks_are_ignored(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        $payment = $this->pendingPayment();
        $data = ['reference' => $payment->provider_reference, 'status' => 'succeeded'];

        $this->sendCallback($payment->provider, $data)->assertOk();
        $this->sendCallback($payment->provider, $data)->assertOk();

        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertSame(1, WebhookDelivery::count());
    }

    public function test_failure_after_success_is_a_logged_conflict(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        Log::spy();
        $payment = $this->pendingPayment();

        $this->sendCallback($payment->provider, ['reference' => $payment->provider_reference, 'status' => 'succeeded'])->assertOk();
        $this->sendCallback($payment->provider, ['reference' => $payment->provider_reference, 'status' => 'failed'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'payment_not_pending');

        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        Log::shouldHaveReceived('error')->once();
    }

    public function test_success_after_cancel_is_a_logged_conflict_not_silently_accepted(): void
    {
        Log::spy();
        $payment = $this->pendingPayment();
        app(PaymentService::class)->cancel($payment);

        $this->sendCallback($payment->provider, ['reference' => $payment->provider_reference, 'status' => 'succeeded'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'payment_not_pending');

        $this->assertSame(PaymentStatus::Canceled, $payment->fresh()->status);
        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context) => $context['payment_id'] === $payment->id
            && $context['payment_status'] === 'canceled'
            && $context['callback_outcome'] === 'succeeded')->once();
    }

    public function test_failure_after_cancel_is_accepted_quietly(): void
    {
        $payment = $this->pendingPayment();
        app(PaymentService::class)->cancel($payment);

        $this->sendCallback($payment->provider, ['reference' => $payment->provider_reference, 'status' => 'failed'])->assertOk();
    }

    public function test_unknown_reference_asks_the_provider_to_retry(): void
    {
        $payment = $this->pendingPayment();

        $this->sendCallback($payment->provider, ['reference' => 'sim_not_committed_yet', 'status' => 'succeeded'])
            ->assertStatus(503)
            ->assertHeader('Retry-After')
            ->assertJsonPath('error.code', 'payment_not_ready');
    }

    public function test_provider_cannot_complete_another_providers_payment(): void
    {
        $payment = $this->pendingPayment();
        $other = Provider::factory()->create();

        $this->sendCallback($other, ['reference' => $payment->provider_reference, 'status' => 'succeeded'])
            ->assertStatus(503);

        $this->assertSame(PaymentStatus::RequiresAction, $payment->fresh()->status);
    }

    public function test_unknown_provider_is_not_found(): void
    {
        $this->postJson('/api/provider-callbacks/nope', [])->assertNotFound();
    }

    public function test_callbacks_are_rejected_without_a_simulator_secret(): void
    {
        $payment = $this->pendingPayment();
        $body = ['reference' => $payment->provider_reference, 'status' => 'succeeded'];
        $signature = SimulatedProcessor::sign(json_encode($body));
        config(['gateway.simulator_callback_secret' => null]);

        $this->sendCallback($payment->provider, $body, $signature)->assertStatus(401);
        $this->sendCallback($payment->provider, $body, hash_hmac('sha256', json_encode($body), ''))->assertStatus(401);

        $this->assertSame(PaymentStatus::RequiresAction, $payment->fresh()->status);
    }

    public function test_simulated_provider_is_unreachable_when_its_driver_is_not_registered(): void
    {
        $payment = $this->pendingPayment();
        config(['gateway.provider_drivers' => []]);

        $this->sendCallback($payment->provider, ['reference' => $payment->provider_reference, 'status' => 'succeeded'])
            ->assertNotFound();
    }

    public function test_pending_payments_expire(): void
    {
        $payment = $this->pendingPayment();
        $this->assertTrue($payment->expires_at->between(now()->addMinutes(29), now()->addMinutes(31)));

        $this->artisan('payments:expire-pending')->assertSuccessful();
        $this->assertSame(PaymentStatus::RequiresAction, $payment->fresh()->status);

        $this->travel(31)->minutes();
        $this->artisan('payments:expire-pending')->expectsOutputToContain('1 pending payment(s) expired.');

        $payment->refresh();
        $this->assertSame(PaymentStatus::Canceled, $payment->status);
        $this->assertSame('expired', $payment->failure_code);
        $this->assertSame(0, $payment->fee_amount);
        $this->assertNull($payment->next_action);

        // A late success after expiry is flagged, not silently accepted.
        $this->sendCallback($payment->provider, ['reference' => $payment->provider_reference, 'status' => 'succeeded'])
            ->assertStatus(409);
    }
}
