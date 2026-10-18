<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Development data. Safe to run more than once. Model events stay on:
 * Merchant generates its webhook secret in a creating hook.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->first()
            ?? User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);

        if (! $user->hasRole('merchant')) {
            $user->assignRole('merchant');
        }

        if (! User::where('email', 'admin@example.com')->exists()) {
            User::factory()->admin()->create(['name' => 'Admin', 'email' => 'admin@example.com']);
        }

        // Two simulated providers offering overlapping methods at different prices.
        $alpha = $this->provider('sim_alpha', 'SimBank Alpha', [
            PaymentMethod::Card->value => [290, 1500],
            PaymentMethod::Qrph->value => [150, 0],
            PaymentMethod::Instapay->value => [0, 1500],
            PaymentMethod::Pesonet->value => [0, 1000],
        ]);
        $beta = $this->provider('sim_beta', 'SimPay Beta', [
            PaymentMethod::Qrph->value => [120, 0],
            PaymentMethod::Gcash->value => [230, 0],
            PaymentMethod::Maya->value => [220, 0],
            PaymentMethod::Ecpay->value => [0, 2000],
        ]);

        $merchant = $user->merchant ?? $user->merchant()->create(['name' => $user->name]);

        foreach ([$alpha, $beta] as $provider) {
            foreach ($provider->paymentMethods as $pricing) {
                // QRPH is offered by both; the cheaper Beta wins (seeded last).
                $merchant->paymentMethodAssignments()->updateOrCreate(
                    ['payment_method' => $pricing->payment_method->value],
                    ['provider_payment_method_id' => $pricing->id],
                );
            }
        }
    }

    /**
     * @param  array<string, array{int, int}>  $pricing  method => [fee_bps, fee_fixed]
     */
    private function provider(string $code, string $name, array $pricing): Provider
    {
        $provider = Provider::updateOrCreate(['code' => $code], ['name' => $name, 'driver' => 'simulated', 'is_active' => true]);

        foreach ($pricing as $method => [$bps, $fixed]) {
            $provider->paymentMethods()->updateOrCreate(['payment_method' => $method], ['fee_bps' => $bps, 'fee_fixed' => $fixed]);
        }

        return $provider->load('paymentMethods');
    }
}
