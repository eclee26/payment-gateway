<?php

namespace Database\Factories;

use App\Models\ApiKey;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // A merchant ready to take card payments, the common API case.
            'merchant_id' => Merchant::factory()->withPaymentMethods(),
            'key_id' => 'key_'.bin2hex(random_bytes(12)),
            'secret' => 'sk_'.bin2hex(random_bytes(32)),
            'name' => null,
        ];
    }

    /**
     * Indicate that the key has been revoked.
     */
    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }
}
