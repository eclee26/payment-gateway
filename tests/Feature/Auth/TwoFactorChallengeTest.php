<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    private function code(User $user): string
    {
        return (new Google2FA)->getCurrentOtp($user->two_factor_secret);
    }

    private function passwordStep(User $user, bool $remember = false): void
    {
        $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => $remember ? 'on' : null])
            ->assertRedirect(route('two-factor.challenge'));
    }

    public function test_correct_password_leads_to_the_challenge_not_a_session(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => 'on']);

        $response->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        // No remember-me cookie may exist before TOTP is passed.
        $response->assertCookieMissing(Auth::guard('web')->getRecallerName());

        $this->get('/two-factor-challenge')->assertOk()->assertSee('Authentication code');
    }

    public function test_challenge_without_a_password_step_goes_back_to_login(): void
    {
        $this->get('/two-factor-challenge')->assertRedirect('/login');
        $this->post('/two-factor-challenge', ['code' => '123456'])->assertRedirect('/login');
    }

    public function test_valid_code_logs_the_user_in(): void
    {
        $user = User::factory()->create();
        $this->passwordStep($user);

        $this->post('/two-factor-challenge', ['code' => $this->code($user)])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_remember_me_is_applied_after_the_challenge(): void
    {
        $user = User::factory()->create();
        $this->passwordStep($user, remember: true);

        $this->post('/two-factor-challenge', ['code' => $this->code($user)])
            ->assertCookie(Auth::guard('web')->getRecallerName());
    }

    public function test_invalid_code_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->passwordStep($user);

        $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_a_code_cannot_be_replayed(): void
    {
        $user = User::factory()->create();
        $code = $this->code($user);

        $this->passwordStep($user);
        $this->post('/two-factor-challenge', ['code' => $code]);
        $this->assertAuthenticatedAs($user);
        $this->post('/logout');

        $this->passwordStep($user);
        $this->post('/two-factor-challenge', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_recovery_code_works_once(): void
    {
        $user = User::factory()->create();

        $this->passwordStep($user);
        $this->post('/two-factor-challenge', ['recovery_code' => 'aaaaa-11111']);
        $this->assertAuthenticatedAs($user);
        $this->assertNotContains('aaaaa-11111', $user->fresh()->two_factor_recovery_codes);
        $this->post('/logout');

        $this->passwordStep($user);
        $this->post('/two-factor-challenge', ['recovery_code' => 'aaaaa-11111'])->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }

    public function test_challenge_is_rate_limited(): void
    {
        $user = User::factory()->create();
        $this->passwordStep($user);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/two-factor-challenge', ['code' => '000000']);
        }

        // Even the right code is refused while locked out.
        $this->post('/two-factor-challenge', ['code' => $this->code($user)])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_wrong_password_never_reaches_the_challenge(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');

        $this->get('/two-factor-challenge')->assertRedirect('/login');
    }
}
