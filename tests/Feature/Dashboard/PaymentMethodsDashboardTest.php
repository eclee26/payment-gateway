<?php

namespace Tests\Feature\Dashboard;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodsDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function pendingPaymentFor(Merchant $merchant): Payment
    {
        $provider = Provider::factory()->offering([PaymentMethod::Gcash])->create();

        return Payment::factory()->for($merchant)->create([
            'payment_method' => PaymentMethod::Gcash,
            'provider_id' => $provider->id,
            'provider_reference' => 'sim_ref_'.$merchant->id,
            'currency' => 'PHP',
            'status' => PaymentStatus::RequiresAction,
            'amount_captured' => 0,
            'card_brand' => null,
            'card_last4' => null,
            'card_exp_month' => null,
            'card_exp_year' => null,
            'card_fingerprint' => null,
            'next_action' => ['type' => 'redirect', 'url' => 'https://simulator.invalid/x'],
        ]);
    }

    public function test_merchant_sees_only_their_own_assignments(): void
    {
        $mine = Provider::factory()->offering([PaymentMethod::Gcash])->create(['name' => 'Mine Bank']);
        $theirs = Provider::factory()->offering([PaymentMethod::Gcash])->create(['name' => 'Their Bank']);
        $merchant = Merchant::factory()->withPaymentMethods([$mine->paymentMethods()->sole()])->create();
        Merchant::factory()->withPaymentMethods([$theirs->paymentMethods()->sole()])->create();

        $this->actingAs($merchant->user)
            ->get('/dashboard/payment-methods')
            ->assertOk()
            ->assertSee('Mine Bank')
            ->assertDontSee('Their Bank');
    }

    public function test_payment_methods_page_requires_login(): void
    {
        $this->get('/dashboard/payment-methods')->assertRedirect('/login');
    }

    public function test_merchant_can_simulate_their_pending_payment(): void
    {
        $merchant = Merchant::factory()->create();
        $payment = $this->pendingPaymentFor($merchant);

        $this->actingAs($merchant->user)
            ->get("/dashboard/payments/{$payment->id}")
            ->assertOk()
            ->assertSee('Simulate success');

        $this->actingAs($merchant->user)
            ->post("/dashboard/payments/{$payment->id}/simulate", ['outcome' => 'succeeded'])
            ->assertRedirect("/dashboard/payments/{$payment->id}");

        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
    }

    public function test_cannot_simulate_another_merchants_payment(): void
    {
        $payment = $this->pendingPaymentFor(Merchant::factory()->create());
        $intruder = Merchant::factory()->create()->user;

        $this->actingAs($intruder)
            ->post("/dashboard/payments/{$payment->id}/simulate", ['outcome' => 'succeeded'])
            ->assertNotFound();

        $this->assertSame(PaymentStatus::RequiresAction, $payment->fresh()->status);
    }

    public function test_simulator_is_unavailable_when_disabled(): void
    {
        config(['gateway.simulator_enabled' => false]);
        $merchant = Merchant::factory()->create();
        $payment = $this->pendingPaymentFor($merchant);

        $this->actingAs($merchant->user)
            ->get("/dashboard/payments/{$payment->id}")
            ->assertOk()
            ->assertDontSee('Simulate success');
        $this->actingAs($merchant->user)
            ->post("/dashboard/payments/{$payment->id}/simulate", ['outcome' => 'succeeded'])
            ->assertNotFound();

        $this->assertSame(PaymentStatus::RequiresAction, $payment->fresh()->status);
    }

    public function test_docs_and_postman_include_payment_method(): void
    {
        $user = Merchant::factory()->create()->user;

        $this->actingAs($user)
            ->get('/dashboard/docs')
            ->assertOk()
            ->assertSee('id="payment-methods"', false)
            ->assertSee('payment_method_unavailable')
            ->assertSee('requires_action');

        $collection = $this->actingAs($user)->get('/dashboard/docs/postman')->assertOk()->json();
        $create = $collection['item'][0]['item'][0];
        $this->assertSame('CARD', json_decode($create['request']['body']['raw'], true)['payment_method']);
    }

    public function test_non_card_payments_render_in_the_payment_list(): void
    {
        $merchant = Merchant::factory()->create();
        $this->pendingPaymentFor($merchant);

        $this->actingAs($merchant->user)->get('/dashboard/payments')->assertOk()->assertSee('GCash');
    }
}
