<?php

namespace Database\Factories;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state. Users have TOTP set up, since it is
     * mandatory; use withoutTwoFactor() for a user who hasn't yet.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => (new Google2FA)->generateSecretKey(32),
            'two_factor_recovery_codes' => ['aaaaa-11111', 'bbbbb-22222'],
            'two_factor_confirmed_at' => now(),
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            if (! $user->roles()->exists()) {
                $user->assignRole(Permissions::MERCHANT);
            }
        });
    }

    /**
     * An admin who does not operate a merchant account.
     */
    public function admin(): static
    {
        return $this->afterCreating(fn (User $user) => $user->syncRoles(['admin']));
    }

    /**
     * A super-admin: every permission, through Gate::before.
     */
    public function superAdmin(): static
    {
        return $this->afterCreating(fn (User $user) => $user->syncRoles([Permissions::SUPER_ADMIN]));
    }

    /**
     * A user who has not set up TOTP yet.
     */
    public function withoutTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
