<?php

namespace Tests\Feature\Api;

use App\Enums\PaymentStatus;
use App\Models\ApiKey;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SignsApiRequests;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase, SignsApiRequests;

    public function test_successful_payment_is_captured_immediately(): void
    {
        $key = ApiKey::factory()->create();

        $this->api($key, 'POST', '/api/v1/payments', $this->paymentPayload())
            ->assertCreated()
            ->assertJsonPath('object', 'payment')
            ->assertJsonPath('status', 'succeeded')
            ->assertJsonPath('amount', 2500)
            ->assertJsonPath('amount_captured', 2500)
            ->assertJsonPath('amount_refunded', 0)
            ->assertJsonPath('currency', 'USD')
            ->assertJsonPath('card.brand', 'visa')
            ->assertJsonPath('card.last4', '4242');

        $this->assertMatchesRegularExpression('/^pay_[0-9a-z]{26}$/', Payment::sole()->id);
    }

    public function test_raw_card_number_and_cvc_are_never_stored_or_returned(): void
    {
        $key = ApiKey::factory()->create();
        $payload = $this->paymentPayload(['card' => ['number' => '4242 4242 4242 4242', 'cvc' => '987']]);

        $response = $this->api($key, 'POST', '/api/v1/payments', $payload)->assertCreated();

        $this->assertStringNotContainsString('4242424242424242', $response->getContent());
        $this->assertStringNotContainsString('987', json_encode($response->json('card')));
        $this->assertArrayNotHasKey('card_fingerprint', $response->json());

        $rows = json_encode([
            DB::table('payments')->get(),
            DB::table('idempotency_keys')->get(['response_body']),
        ]);
        $this->assertStringNotContainsString('4242424242424242', $rows);
        $this->assertStringNotContainsString('987', DB::table('payments')->value('card_last4').DB::table('payments')->value('description'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function decliningCards(): array
    {
        return [
            'generic decline' => ['4000000000000002', 'card_declined'],
            'insufficient funds' => ['4000000000009995', 'insufficient_funds'],
            'expired card' => ['4000000000000069', 'expired_card'],
        ];
    }

    #[DataProvider('decliningCards')]
    public function test_declined_card_returns_402_and_records_a_failed_payment(string $number, string $code): void
    {
        $key = ApiKey::factory()->create();

        $this->api($key, 'POST', '/api/v1/payments', $this->paymentPayload(['card' => ['number' => $number]]))
            ->assertStatus(402)
            ->assertJsonPath('error.type', 'card_error')
            ->assertJsonPath('error.code', $code)
            ->assertJsonPath('payment.status', 'failed');

        $this->assertSame(PaymentStatus::Failed, Payment::sole()->status);
        $this->assertSame(0, Payment::sole()->amount_captured);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'luhn failure' => [['card' => ['number' => '4242424242424241']], 'card.number'],
            'too short' => [['card' => ['number' => '4242']], 'card.number'],
            'expired year' => [['card' => ['exp_year' => 2020]], 'card.exp_year'],
            'bad month' => [['card' => ['exp_month' => 13]], 'card.exp_month'],
            'bad cvc' => [['card' => ['cvc' => '12a']], 'card.cvc'],
            'unsupported currency' => [['currency' => 'XYZ'], 'currency'],
            'zero amount' => [['amount' => 0], 'amount'],
            'too large amount' => [['amount' => 100_000_000], 'amount'],
            'fractional amount' => [['amount' => 10.5], 'amount'],
            'bad capture method' => [['capture_method' => 'later'], 'capture_method'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidPayloads')]
    public function test_invalid_payment_requests_are_rejected(array $overrides, string $field): void
    {
        $key = ApiKey::factory()->create();

        $this->api($key, 'POST', '/api/v1/payments', $this->paymentPayload($overrides))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonValidationErrors($field);

        $this->assertSame(0, Payment::count());
    }

    public function test_card_expired_earlier_this_year_is_rejected(): void
    {
        $this->travelTo(now()->setDate(now()->year, 6, 15));
        $key = ApiKey::factory()->create();

        $this->api($key, 'POST', '/api/v1/payments', $this->paymentPayload(['card' => ['exp_month' => 5, 'exp_year' => now()->year]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('card.exp_month');
    }

    public function test_unexpected_card_fields_are_rejected(): void
    {
        $key = ApiKey::factory()->create();

        $this->api($key, 'POST', '/api/v1/payments', $this->paymentPayload(['card' => ['pin' => '1234']]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('card');
    }

    public function test_validation_errors_do_not_echo_the_card_number(): void
    {
        $key = ApiKey::factory()->create();

        $response = $this->api($key, 'POST', '/api/v1/payments', $this->paymentPayload(['card' => ['number' => '4242424242424241']]))
            ->assertStatus(422);

        $this->assertStringNotContainsString('4242424242424241', $response->getContent());
    }

    public function test_non_json_bodies_are_rejected(): void
    {
        $key = ApiKey::factory()->create();

        $this->api($key, 'POST', '/api/v1/payments', $this->paymentPayload(), ['Content-Type' => 'application/x-www-form-urlencoded'])
            ->assertStatus(415)
            ->assertJsonPath('error.code', 'unsupported_media_type');
    }

    public function test_manual_capture_flow(): void
    {
        $key = ApiKey::factory()->create();

        $id = $this->api($key, 'POST', '/api/v1/payments', $this->paymentPayload(['capture_method' => 'manual']))
            ->assertCreated()
            ->assertJsonPath('status', 'requires_capture')
            ->assertJsonPath('amount_captured', 0)
            ->json('id');

        $this->api($key, 'POST', "/api/v1/payments/{$id}/capture", [])
            ->assertOk()
            ->assertJsonPath('status', 'succeeded')
            ->assertJsonPath('amount_captured', 2500);
    }

    public function test_partial_capture(): void
    {
        $key = ApiKey::factory()->create();
        $payment = Payment::factory()->requiresCapture()->for($key->merchant)->create(['amount' => 1000]);

        $this->api($key, 'POST', "/api/v1/payments/{$payment->id}/capture", ['amount' => 600])
            ->assertOk()
            ->assertJsonPath('amount_captured', 600);
    }

    public function test_capture_cannot_exceed_the_authorized_amount(): void
    {
        $key = ApiKey::factory()->create();
        $payment = Payment::factory()->requiresCapture()->for($key->merchant)->create(['amount' => 1000]);

        $this->api($key, 'POST', "/api/v1/payments/{$payment->id}/capture", ['amount' => 1001])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'amount_too_large');

        $this->assertSame(PaymentStatus::RequiresCapture, $payment->fresh()->status);
    }

    public function test_payment_cannot_be_captured_twice(): void
    {
        $key = ApiKey::factory()->create();
        $payment = Payment::factory()->requiresCapture()->for($key->merchant)->create();

        $this->api($key, 'POST', "/api/v1/payments/{$payment->id}/capture", [])->assertOk();
        $this->api($key, 'POST', "/api/v1/payments/{$payment->id}/capture", [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_state');
    }

    public function test_cancel_releases_an_authorization(): void
    {
        $key = ApiKey::factory()->create();
        $payment = Payment::factory()->requiresCapture()->for($key->merchant)->create();

        $this->api($key, 'POST', "/api/v1/payments/{$payment->id}/cancel")
            ->assertOk()
            ->assertJsonPath('status', 'canceled');

        $this->api($key, 'POST', "/api/v1/payments/{$payment->id}/capture", [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_state');
    }

    public function test_captured_payment_cannot_be_canceled(): void
    {
        $key = ApiKey::factory()->create();
        $payment = Payment::factory()->for($key->merchant)->create();

        $this->api($key, 'POST', "/api/v1/payments/{$payment->id}/cancel")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_state');
    }

    public function test_partial_then_full_refund(): void
    {
        $key = ApiKey::factory()->create();
        $payment = Payment::factory()->for($key->merchant)->create(['amount' => 1000, 'amount_captured' => 1000]);

        $this->api($key, 'POST', "/api/v1/payments/{$payment->id}/refunds", ['amount' => 300, 'reason' => 'requested_by_customer'])
            ->assertCreated()
            ->assertJsonPath('object', 'refund')
            ->assertJsonPath('amount', 300);
        $this->assertSame(PaymentStatus::PartiallyRefunded, $payment->fresh()->status);

        // No amount refunds whatever remains.
        $this->api($key, 'POST', "/api/v1/payments/{$payment->id}/refunds", [])
            ->assertCreated()
            ->assertJsonPath('amount', 700);

        $payment->refresh();
        $this->assertSame(PaymentStatus::Refunded, $payment->status);
        $this->assertSame(1000, $payment->amount_refunded);

        $this->api($key, 'GET', "/api/v1/payments/{$payment->id}/refunds")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_refund_cannot_exceed_the_refundable_amount(): void
    {
        $key = ApiKey::factory()->create();
        $payment = Payment::factory()->for($key->merchant)->create(['amount' => 1000, 'amount_captured' => 1000, 'amount_refunded' => 800, 'status' => PaymentStatus::PartiallyRefunded]);

        $this->api($key, 'POST', "/api/v1/payments/{$payment->id}/refunds", ['amount' => 201])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'amount_too_large');

        $this->assertSame(800, $payment->fresh()->amount_refunded);
    }

    public function test_partial_capture_limits_refunds_to_the_captured_amount(): void
    {
        $key = ApiKey::factory()->create();
        $payment = Payment::factory()->requiresCapture()->for($key->merchant)->create(['amount' => 1000]);

        $this->api($key, 'POST', "/api/v1/payments/{$payment->id}/capture", ['amount' => 500])->assertOk();

        $this->api($key, 'POST', "/api/v1/payments/{$payment->id}/refunds", ['amount' => 501])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'amount_too_large');
    }

    /**
     * @return array<string, array{PaymentStatus}>
     */
    public static function unrefundableStatuses(): array
    {
        return [
            'failed' => [PaymentStatus::Failed],
            'requires capture' => [PaymentStatus::RequiresCapture],
            'canceled' => [PaymentStatus::Canceled],
            'already refunded' => [PaymentStatus::Refunded],
        ];
    }

    #[DataProvider('unrefundableStatuses')]
    public function test_refund_is_rejected_for_unrefundable_payments(PaymentStatus $status): void
    {
        $key = ApiKey::factory()->create();
        $payment = Payment::factory()->for($key->merchant)->create(['status' => $status]);

        $this->api($key, 'POST', "/api/v1/payments/{$payment->id}/refunds", ['amount' => 1])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_state');

        $this->assertSame(0, $payment->refunds()->count());
    }

    public function test_list_is_paginated_and_newest_first(): void
    {
        $key = ApiKey::factory()->create();
        $old = Payment::factory()->for($key->merchant)->create(['created_at' => now()->subDay()]);
        $new = Payment::factory()->for($key->merchant)->create();

        $this->api($key, 'GET', '/api/v1/payments?limit=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $new->id);

        $this->assertNotSame($old->id, $new->id);
    }

    public function test_unknown_payment_returns_404_envelope(): void
    {
        $key = ApiKey::factory()->create();

        $this->api($key, 'GET', '/api/v1/payments/pay_doesnotexist')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'resource_missing');
    }
}
