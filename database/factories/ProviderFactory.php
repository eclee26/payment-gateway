<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Provider>
 */
class ProviderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'prov_'.fake()->unique()->lexify('????????'),
            'name' => fake()->company().' Bank',
            'driver' => 'simulated',
            'is_active' => true,
        ];
    }

    /**
     * Offer these payment methods, all at the same pricing.
     */
    public function offering(array $methods, int $feeBps = 250, int $feeFixed = 0): static
    {
        return $this->afterCreating(function (Provider $provider) use ($methods, $feeBps, $feeFixed) {
            foreach ($methods as $method) {
                $provider->paymentMethods()->create([
                    'payment_method' => $method instanceof PaymentMethod ? $method->value : $method,
                    'fee_bps' => $feeBps,
                    'fee_fixed' => $feeFixed,
                ]);
            }
        });
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
