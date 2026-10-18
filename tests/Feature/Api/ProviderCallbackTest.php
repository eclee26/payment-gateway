<?php

namespace Tests\Feature\Api;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\ApiKey;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\Provider;
use App\Models\WebhookDelivery;
use App\Services\Processor\SimulatedProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SignsApiRequests;
use Tests\TestCase;

class ProviderCallbackTest extends TestCase
{
    use RefreshDatabase, SignsApiRequests;

    private function pendingPayment(): Payment
    {
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
        $this->sendCallback($payment->provider, ['reference' => $payment->provider_reference, 'status' => 'failed'])->assertOk();

        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertSame(1, WebhookDelivery::count());
    }

    public function test_provider_cannot_complete_another_providers_payment(): void
    {
        $payment = $this->pendingPayment();
        $other = Provider::factory()->create();

        $this->sendCallback($other, ['reference' => $payment->provider_reference, 'status' => 'succeeded'])
            ->assertNotFound();

        $this->assertSame(PaymentStatus::RequiresAction, $payment->fresh()->status);
    }

    public function test_unknown_provider_is_not_found(): void
    {
        $this->postJson('/api/provider-callbacks/nope', [])->assertNotFound();
    }
}
