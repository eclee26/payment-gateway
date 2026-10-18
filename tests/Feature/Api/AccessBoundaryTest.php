<?php

namespace Tests\Feature\Api;

use App\Enums\PaymentStatus;
use App\Models\ApiKey;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsApiRequests;
use Tests\TestCase;

class AccessBoundaryTest extends TestCase
{
    use RefreshDatabase, SignsApiRequests;

    private ApiKey $otherMerchantKey;

    private Payment $authorized;

    private Payment $captured;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = ApiKey::factory()->create();
        $this->authorized = Payment::factory()->requiresCapture()->for($owner->merchant)->create();
        $this->captured = Payment::factory()->for($owner->merchant)->create();

        $this->otherMerchantKey = ApiKey::factory()->create();
    }

    public function test_other_merchant_cannot_view_the_payment(): void
    {
        $this->api($this->otherMerchantKey, 'GET', "/api/v1/payments/{$this->captured->id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'resource_missing');
    }

    public function test_other_merchant_cannot_capture_the_payment(): void
    {
        $this->api($this->otherMerchantKey, 'POST', "/api/v1/payments/{$this->authorized->id}/capture", [])->assertNotFound();

        $this->assertSame(PaymentStatus::RequiresCapture, $this->authorized->fresh()->status);
    }

    public function test_other_merchant_cannot_cancel_the_payment(): void
    {
        $this->api($this->otherMerchantKey, 'POST', "/api/v1/payments/{$this->authorized->id}/cancel")->assertNotFound();

        $this->assertSame(PaymentStatus::RequiresCapture, $this->authorized->fresh()->status);
    }

    public function test_other_merchant_cannot_refund_the_payment(): void
    {
        $this->api($this->otherMerchantKey, 'POST', "/api/v1/payments/{$this->captured->id}/refunds", ['amount' => 1])->assertNotFound();

        $this->assertSame(0, $this->captured->fresh()->amount_refunded);
        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_other_merchant_cannot_list_the_payments_refunds(): void
    {
        $this->api($this->otherMerchantKey, 'GET', "/api/v1/payments/{$this->captured->id}/refunds")->assertNotFound();
    }

    public function test_list_only_contains_the_merchants_own_payments(): void
    {
        $own = Payment::factory()->for($this->otherMerchantKey->merchant)->create();

        $this->api($this->otherMerchantKey, 'GET', '/api/v1/payments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }
}
