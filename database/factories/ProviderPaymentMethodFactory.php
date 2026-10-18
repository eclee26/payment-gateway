<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Provider;
use App\Models\ProviderPaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderPaymentMethod>
 */
class ProviderPaymentMethodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_id' => Provider::factory(),
            'payment_method' => PaymentMethod::Card,
            'fee_bps' => 250,
            'fee_fixed' => 0,
            'is_active' => true,
        ];
    }
}
