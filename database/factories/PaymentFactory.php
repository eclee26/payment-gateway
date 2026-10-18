<?php

namespace Database\Factories;

use App\Enums\CaptureMethod;
use App\Enums\PaymentStatus;
use App\Models\Merchant;
use App\Models\Payment;
use App\Support\Card;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->numberBetween(100, 100_000);

        return [
            'merchant_id' => Merchant::factory(),
            'amount' => $amount,
            'currency' => 'USD',
            'status' => PaymentStatus::Succeeded,
            'capture_method' => CaptureMethod::Automatic,
            'amount_captured' => $amount,
            'amount_refunded' => 0,
            'card_brand' => 'visa',
            'card_last4' => '4242',
            'card_exp_month' => 12,
            'card_exp_year' => now()->year + 2,
            'card_fingerprint' => Card::fingerprint('4242424242424242'),
        ];
    }

    /**
     * An authorized payment awaiting manual capture.
     */
    public function requiresCapture(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::RequiresCapture,
            'capture_method' => CaptureMethod::Manual,
            'amount_captured' => 0,
        ]);
    }
}
