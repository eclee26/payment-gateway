<?php

namespace Tests\Feature\Api;

use App\Enums\PaymentMethod;
use App\Models\ApiKey;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\Provider;
use App\Models\ProviderPaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsApiRequests;
use Tests\TestCase;

class PaymentMethodRoutingTest extends TestCase
{
    use RefreshDatabase, SignsApiRequests;

    /**
     * An API key for a merchant with exactly these assignments.
     *
     * @param  list<PaymentMethod|ProviderPaymentMethod>  $methods
     */
    private function keyWith(array $methods): ApiKey
    {
        return ApiKey::factory()->for(Merchant::factory()->withPaymentMethods($methods))->create();
    }

    private function pricing(Provider $provider, PaymentMethod $method): ProviderPaymentMethod
    {
        return $provider->paymentMethods()->where('payment_method', $method->value)->sole();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function walletPayload(string $method = 'GCASH', array $overrides = []): array
    {
        return array_merge(['payment_method' => $method, 'amount' => 10_000, 'currency' => 'PHP'], $overrides);
    }

    public function test_each_merchant_uses_its_own_provider_for_the_same_method(): void
    {
        $alpha = Provider::factory()->offering([PaymentMethod::Qrph], feeBps: 150)->create();
        $beta = Provider::factory()->offering([PaymentMethod::Qrph], feeBps: 120)->create();
        $keyA = $this->keyWith([$this->pricing($alpha, PaymentMethod::Qrph)]);
        $keyB = $this->keyWith([$this->pricing($beta, PaymentMethod::Qrph)]);

        $this->api($keyA, 'POST', '/api/v1/payments', $this->walletPayload('QRPH'))
            ->assertCreated()
            ->assertJsonPath('provider', $alpha->code)
            ->assertJsonPath('fee_amount', 150);
        $this->api($keyB, 'POST', '/api/v1/payments', $this->walletPayload('QRPH'))
            ->assertCreated()
            ->assertJsonPath('provider', $beta->code)
            ->assertJsonPath('fee_amount', 120);

        $this->assertSame($alpha->id, Payment::where('merchant_id', $keyA->merchant_id)->sole()->provider_id);
        $this->assertSame($beta->id, Payment::where('merchant_id', $keyB->merchant_id)->sole()->provider_id);
    }

    public function test_different_methods_route_to_different_providers(): void
    {
        $alpha = Provider::factory()->offering([PaymentMethod::Qrph])->create();
        $beta = Provider::factory()->offering([PaymentMethod::Gcash])->create();
        $key = $this->keyWith([$this->pricing($alpha, PaymentMethod::Qrph), $this->pricing($beta, PaymentMethod::Gcash)]);

        $this->api($key, 'POST', '/api/v1/payments', $this->walletPayload('QRPH'))->assertJsonPath('provider', $alpha->code);
        $this->api($key, 'POST', '/api/v1/payments', $this->walletPayload('GCASH'))->assertJsonPath('provider', $beta->code);
    }

    public function test_async_methods_wait_for_customer_action(): void
    {
        $key = $this->keyWith([PaymentMethod::Gcash, PaymentMethod::Qrph, PaymentMethod::Ecpay]);

        $this->api($key, 'POST', '/api/v1/payments', $this->walletPayload('gcash'))
            ->assertCreated()
            ->assertJsonPath('payment_method', 'GCASH')
            ->assertJsonPath('status', 'requires_action')
            ->assertJsonPath('amount_captured', 0)
            ->assertJsonPath('card', null)
            ->assertJsonPath('next_action.type', 'redirect');

        $this->api($key, 'POST', '/api/v1/payments', $this->walletPayload('QRPH'))->assertJsonPath('next_action.type', 'qr_code');
        $this->api($key, 'POST', '/api/v1/payments', $this->walletPayload('ECPAY'))->assertJsonPath('next_action.type', 'display_reference');
    }

    public function test_fee_combines_percentage_and_fixed_amount(): void
    {
        $provider = Provider::factory()->offering([PaymentMethod::Card], feeBps: 290, feeFixed: 1500)->create();
        $key = $this->keyWith([$this->pricing($provider, PaymentMethod::Card)]);

        // 2.9% of 2500 = 72.5 → 73, plus 1500.
        $this->api($key, 'POST', '/api/v1/payments', $this->paymentPayload())
            ->assertCreated()
            ->assertJsonPath('fee_amount', 1573);
    }

    public function test_declined_payment_has_no_fee(): void
    {
        $key = ApiKey::factory()->create();

        $this->api($key, 'POST', '/api/v1/payments', $this->paymentPayload(['card' => ['number' => '4000000000000002']]))
            ->assertStatus(402);

        $this->assertSame(0, Payment::sole()->fee_amount);
    }

    public function test_unassigned_method_is_rejected(): void
    {
        $key = ApiKey::factory()->create(); // CARD only

        $this->api($key, 'POST', '/api/v1/payments', $this->walletPayload())
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'payment_method_unavailable');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_method_assigned_to_another_merchant_is_not_usable(): void
    {
        $this->keyWith([PaymentMethod::Gcash]);
        $key = ApiKey::factory()->create();

        $this->api($key, 'POST', '/api/v1/payments', $this->walletPayload())
            ->assertJsonPath('error.code', 'payment_method_unavailable');
    }

    public function test_inactive_provider_or_method_is_rejected(): void
    {
        $key = $this->keyWith([PaymentMethod::Gcash, PaymentMethod::Maya]);
        $pricing = $key->merchant->paymentMethodAssignments()->with('providerPaymentMethod.provider')->get()
            ->keyBy(fn ($a) => $a->payment_method->value);
        $pricing['GCASH']->providerPaymentMethod->provider->update(['is_active' => false]);
        $pricing['MAYA']->providerPaymentMethod->update(['is_active' => false]);

        $this->api($key, 'POST', '/api/v1/payments', $this->walletPayload('GCASH'))->assertJsonPath('error.code', 'payment_method_unavailable');
        $this->api($key, 'POST', '/api/v1/payments', $this->walletPayload('MAYA'))->assertJsonPath('error.code', 'payment_method_unavailable');
    }

    public function test_payment_method_is_required_and_must_be_known(): void
    {
        $key = ApiKey::factory()->create();
        $payload = $this->paymentPayload();
        unset($payload['payment_method']);

        $this->api($key, 'POST', '/api/v1/payments', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_method');
        $this->api($key, 'POST', '/api/v1/payments', $this->paymentPayload(['payment_method' => 'BITCOIN']))
            ->assertJsonValidationErrors('payment_method');
    }

    public function test_card_details_are_prohibited_for_non_card_methods(): void
    {
        $key = $this->keyWith([PaymentMethod::Gcash]);

        $this->api($key, 'POST', '/api/v1/payments', $this->walletPayload('GCASH', ['card' => $this->paymentPayload()['card']]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('card');
    }

    public function test_card_is_required_for_card_payments(): void
    {
        $key = ApiKey::factory()->create();
        $payload = $this->paymentPayload();
        unset($payload['card']);

        $this->api($key, 'POST', '/api/v1/payments', $payload)
            ->assertJsonValidationErrors('card');
    }

    public function test_non_card_methods_only_accept_php(): void
    {
        $key = $this->keyWith([PaymentMethod::Qrph]);

        $this->api($key, 'POST', '/api/v1/payments', $this->walletPayload('QRPH', ['currency' => 'USD']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('currency');
    }

    public function test_manual_capture_is_only_for_cards(): void
    {
        $key = $this->keyWith([PaymentMethod::Gcash]);

        $this->api($key, 'POST', '/api/v1/payments', $this->walletPayload('GCASH', ['capture_method' => 'manual']))
            ->assertJsonValidationErrors('capture_method');
    }

    public function test_reassignment_does_not_move_existing_payments(): void
    {
        $alpha = Provider::factory()->offering([PaymentMethod::Card])->create();
        $beta = Provider::factory()->offering([PaymentMethod::Card])->create();
        $key = $this->keyWith([$this->pricing($alpha, PaymentMethod::Card)]);

        $id = $this->api($key, 'POST', '/api/v1/payments', $this->paymentPayload(['capture_method' => 'manual']))->json('id');

        $key->merchant->paymentMethodAssignments()->update(['provider_payment_method_id' => $this->pricing($beta, PaymentMethod::Card)->id]);

        $this->api($key, 'POST', "/api/v1/payments/{$id}/capture")
            ->assertOk()
            ->assertJsonPath('provider', $alpha->code);
        $this->api($key, 'POST', '/api/v1/payments', $this->paymentPayload())
            ->assertJsonPath('provider', $beta->code);
    }

    public function test_requires_action_payment_can_be_canceled(): void
    {
        $key = $this->keyWith([PaymentMethod::Gcash]);
        $id = $this->api($key, 'POST', '/api/v1/payments', $this->walletPayload())->json('id');

        $this->api($key, 'POST', "/api/v1/payments/{$id}/cancel")
            ->assertOk()
            ->assertJsonPath('status', 'canceled')
            ->assertJsonPath('next_action', null);
    }
}
