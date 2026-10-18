<?php

namespace Tests\Feature\Dashboard;

use App\Models\ApiKey;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function protectedPages(): array
    {
        return [
            'overview' => ['/dashboard'],
            'payments' => ['/dashboard/payments'],
            'api keys' => ['/dashboard/api-keys'],
            'webhooks' => ['/dashboard/webhooks'],
        ];
    }

    #[DataProvider('protectedPages')]
    public function test_guests_are_redirected_to_login(string $uri): void
    {
        $this->get($uri)->assertRedirect('/login');
    }

    public function test_merchant_is_created_on_first_visit(): void
    {
        $user = User::factory()->create(['name' => 'Acme']);

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Acme');

        $this->assertSame('Acme', $user->fresh()->merchant->name);
    }

    public function test_user_sees_only_their_own_payments(): void
    {
        $mine = Payment::factory()->create();
        $theirs = Payment::factory()->create();

        $this->actingAs($mine->merchant->user)
            ->get('/dashboard/payments')
            ->assertOk()
            ->assertSee($mine->id)
            ->assertDontSee($theirs->id);
    }

    public function test_user_can_view_their_own_payment(): void
    {
        $payment = Payment::factory()->create();

        $this->actingAs($payment->merchant->user)
            ->get("/dashboard/payments/{$payment->id}")
            ->assertOk()
            ->assertSee('4242');
    }

    public function test_user_cannot_view_another_merchants_payment(): void
    {
        $payment = Payment::factory()->create();
        $intruder = Merchant::factory()->create()->user;

        $this->actingAs($intruder)->get("/dashboard/payments/{$payment->id}")->assertNotFound();
    }

    public function test_api_key_pages_require_password_confirmation(): void
    {
        $user = Merchant::factory()->create()->user;

        $this->actingAs($user)->get('/dashboard/api-keys')->assertRedirect('/confirm-password');
        $this->actingAs($user)->post('/dashboard/api-keys')->assertRedirect('/confirm-password');

        $this->assertDatabaseCount('api_keys', 0);
    }

    public function test_created_key_secret_is_shown_once_and_encrypted_at_rest(): void
    {
        $user = Merchant::factory()->create()->user;

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post('/dashboard/api-keys', ['name' => 'Production'])
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');

        $key = ApiKey::sole();
        $response->assertSee($key->key_id)->assertSee($key->secret);
        $this->assertStringStartsWith('sk_', $key->secret);

        $raw = DB::table('api_keys')->value('secret');
        $this->assertNotSame($key->secret, $raw);
        $this->assertStringNotContainsString($key->secret, $raw);

        // The listing never shows the secret again.
        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get('/dashboard/api-keys')
            ->assertOk()
            ->assertSee($key->key_id)
            ->assertDontSee($key->secret);
    }

    public function test_user_can_revoke_their_own_key(): void
    {
        $key = ApiKey::factory()->create();

        $this->actingAs($key->merchant->user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete("/dashboard/api-keys/{$key->id}")
            ->assertRedirect('/dashboard/api-keys');

        $this->assertTrue($key->fresh()->isRevoked());
    }

    public function test_user_cannot_revoke_another_merchants_key(): void
    {
        $key = ApiKey::factory()->create();
        $intruder = Merchant::factory()->create()->user;

        $this->actingAs($intruder)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete("/dashboard/api-keys/{$key->id}")
            ->assertNotFound();

        $this->assertFalse($key->fresh()->isRevoked());
    }

    public function test_webhook_url_must_be_public_https(): void
    {
        $merchant = Merchant::factory()->create();

        $this->actingAs($merchant->user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put('/dashboard/webhooks', ['webhook_url' => 'https://169.254.169.254/latest'])
            ->assertSessionHasErrors('webhook_url');

        $this->assertNull($merchant->fresh()->webhook_url);

        $this->actingAs($merchant->user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put('/dashboard/webhooks', ['webhook_url' => 'https://93.184.215.14/hooks'])
            ->assertSessionHasNoErrors();

        $this->assertSame('https://93.184.215.14/hooks', $merchant->fresh()->webhook_url);
    }

    public function test_rotating_the_webhook_secret_replaces_it(): void
    {
        $merchant = Merchant::factory()->create();
        $old = $merchant->webhook_secret;

        $this->actingAs($merchant->user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post('/dashboard/webhooks/secret')
            ->assertOk()
            ->assertSee($merchant->fresh()->webhook_secret);

        $this->assertNotSame($old, $merchant->fresh()->webhook_secret);
        $this->assertStringNotContainsString($merchant->fresh()->webhook_secret, DB::table('merchants')->value('webhook_secret'));
    }
}
