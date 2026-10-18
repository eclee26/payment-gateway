<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Merchant;
use App\Models\ProviderPaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Merchant>
 */
class MerchantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
        ];
    }

    /**
     * Assign payment methods. Pass ProviderPaymentMethod rows to choose the
     * provider, or PaymentMethod cases to give each its own new simulated
     * provider.
     *
     * @param  list<PaymentMethod|ProviderPaymentMethod>  $methods
     */
    public function withPaymentMethods(array $methods = [PaymentMethod::Card]): static
    {
        return $this->afterCreating(function (Merchant $merchant) use ($methods) {
            foreach ($methods as $method) {
                $pricing = $method instanceof ProviderPaymentMethod
                    ? $method
                    : ProviderPaymentMethod::factory()->create(['payment_method' => $method]);

                $merchant->paymentMethodAssignments()->create([
                    'payment_method' => $pricing->payment_method,
                    'provider_payment_method_id' => $pricing->id,
                ]);
            }
        });
    }
}
