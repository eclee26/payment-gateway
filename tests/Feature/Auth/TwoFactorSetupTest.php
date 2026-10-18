<?php

namespace Tests\Feature\Auth;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_without_totp_is_sent_to_setup(): void
    {
        $user = User::factory()->withoutTwoFactor()->create();
        Merchant::factory()->for($user)->create();

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('two-factor.setup'));
        $this->actingAs($user)->get('/profile')->assertRedirect(route('two-factor.setup'));
    }

    public function test_staff_without_totp_is_sent_to_setup(): void
    {
        $user = User::factory()->withoutTwoFactor()->superAdmin()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get('/admin/users')
            ->assertRedirect(route('two-factor.setup'));
    }

    public function test_login_without_totp_logs_in_and_lands_on_setup(): void
    {
        $user = User::factory()->withoutTwoFactor()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($user);

        $this->get('/dashboard')->assertRedirect(route('two-factor.setup'));
    }

    public function test_setup_page_shows_a_qr_code_and_keeps_the_secret_across_reloads(): void
    {
        $user = User::factory()->withoutTwoFactor()->create();

        $this->actingAs($user)->get('/two-factor/setup')->assertOk()->assertSee('<svg', false);
        $secret = session('two-factor.pending-secret');

        $this->actingAs($user)->get('/two-factor/setup')->assertOk();
        $this->assertSame($secret, session('two-factor.pending-secret'));
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_wrong_code_does_not_enable_totp(): void
    {
        $user = User::factory()->withoutTwoFactor()->create();
        $this->actingAs($user)->get('/two-factor/setup');

        $this->actingAs($user)->post('/two-factor/setup', ['code' => '000000'])->assertSessionHasErrors('code');

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_correct_code_enables_totp_and_shows_recovery_codes_once(): void
    {
        $user = User::factory()->withoutTwoFactor()->create();
        $this->actingAs($user)->get('/two-factor/setup');
        $secret = session('two-factor.pending-secret');

        $this->actingAs($user)
            ->post('/two-factor/setup', ['code' => (new Google2FA)->getCurrentOtp($secret)])
            ->assertRedirect(route('two-factor.recovery-codes'));

        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
        $this->assertSame($secret, $user->two_factor_secret);
        $this->assertCount(8, $user->two_factor_recovery_codes);

        $this->actingAs($user)->get('/two-factor/recovery-codes')->assertOk()->assertSee($user->two_factor_recovery_codes[0]);
        // Flashed once: a reload no longer shows them.
        $this->actingAs($user)->get('/two-factor/recovery-codes')->assertRedirect(route('profile.security'));
    }

    public function test_secret_is_stored_encrypted(): void
    {
        $user = User::factory()->create();

        $raw = DB::table('users')->where('id', $user->id)->value('two_factor_secret');

        $this->assertNotSame($user->two_factor_secret, $raw);
        $this->assertArrayNotHasKey('two_factor_secret', $user->toArray());
    }

    public function test_user_can_log_out_during_setup(): void
    {
        $user = User::factory()->withoutTwoFactor()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_users_with_totp_cannot_restart_setup(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/two-factor/setup')->assertRedirect(route('profile.security'));
        $this->actingAs($user)->post('/two-factor/setup', ['code' => '123456'])->assertRedirect(route('profile.security'));
    }

    public function test_regenerating_recovery_codes_requires_password_confirmation(): void
    {
        $user = User::factory()->create();
        $old = $user->two_factor_recovery_codes;

        $this->actingAs($user)->post('/two-factor/recovery-codes')->assertRedirect('/confirm-password');

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post('/two-factor/recovery-codes')
            ->assertRedirect(route('two-factor.recovery-codes'));

        $this->assertNotEquals($old, $user->fresh()->two_factor_recovery_codes);
    }

    public function test_security_page_shows_status(): void
    {
        $this->actingAs(User::factory()->create())->get('/security')->assertOk()->assertSee('Recovery codes');
    }
}
