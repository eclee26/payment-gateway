<?php

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsApiRequests;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase, SignsApiRequests;

    public function test_requests_beyond_the_per_merchant_limit_are_throttled(): void
    {
        config(['gateway.rate_limit' => 3]);
        $key = ApiKey::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->api($key, 'GET', '/api/v1/payments')->assertOk();
        }

        $this->api($key, 'GET', '/api/v1/payments')
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'rate_limited')
            ->assertHeader('Retry-After');
    }

    public function test_the_limit_is_tracked_separately_per_merchant(): void
    {
        config(['gateway.rate_limit' => 1]);
        $keyA = ApiKey::factory()->create();
        $keyB = ApiKey::factory()->create();

        $this->api($keyA, 'GET', '/api/v1/payments')->assertOk();
        $this->api($keyA, 'GET', '/api/v1/payments')->assertStatus(429);

        $this->api($keyB, 'GET', '/api/v1/payments')->assertOk();
    }
}
