<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentMethod;
use App\Models\Merchant;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private function confirmed(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function adminRoutes(): array
    {
        return [
            'admin home' => ['GET', '/admin'],
            'providers' => ['GET', '/admin/providers'],
            'create provider form' => ['GET', '/admin/providers/create'],
            'store provider' => ['POST', '/admin/providers'],
            'merchants' => ['GET', '/admin/merchants'],
        ];
    }

    #[DataProvider('adminRoutes')]
    public function test_guests_are_redirected_to_login(string $method, string $uri): void
    {
        $this->call($method, $uri)->assertRedirect('/login');
    }

    #[DataProvider('adminRoutes')]
    public function test_merchants_cannot_reach_the_admin_area(string $method, string $uri): void
    {
        $this->confirmed(Merchant::factory()->create()->user)->call($method, $uri)->assertForbidden();
    }

    public function test_merchant_cannot_change_assignments(): void
    {
        $merchant = Merchant::factory()->create();
        $other = Merchant::factory()->create();
        $pricing = Provider::factory()->offering([PaymentMethod::Gcash])->create()->paymentMethods()->sole();

        $this->confirmed($merchant->user)
            ->put("/admin/merchants/{$other->id}/payment-methods", ['assignments' => ['GCASH' => $pricing->id]])
            ->assertForbidden();
        $this->confirmed($merchant->user)
            ->put("/admin/merchants/{$merchant->id}/payment-methods", ['assignments' => ['GCASH' => $pricing->id]])
            ->assertForbidden();

        $this->assertDatabaseCount('merchant_payment_methods', 0);
    }

    public function test_admin_pages_require_password_confirmation(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin/providers')->assertRedirect('/confirm-password');
    }

    public function test_admin_without_assign_permission_cannot_assign(): void
    {
        Role::findByName('admin')->revokePermissionTo('merchant-providers.assign');
        $user = User::factory()->admin()->create();

        $this->confirmed($user)->get('/admin/providers')->assertOk();
        $this->confirmed($user)->get('/admin/merchants')->assertForbidden();
    }

    public function test_admin_creates_a_provider_with_pricing(): void
    {
        $this->confirmed(User::factory()->admin()->create())
            ->post('/admin/providers', [
                'name' => 'Union Test Bank',
                'code' => 'UTB',
                'driver' => 'simulated',
                'is_active' => '1',
                'methods' => [
                    'QRPH' => ['enabled' => '1', 'fee_bps' => '150', 'fee_fixed' => '0'],
                    'GCASH' => ['fee_bps' => '200', 'fee_fixed' => '0'],
                ],
            ])
            ->assertRedirect('/admin/providers');

        $provider = Provider::where('code', 'utb')->sole();
        $this->assertSame(['QRPH'], $provider->paymentMethods->map(fn ($row) => $row->payment_method->value)->all());
        $this->assertSame(150, $provider->paymentMethods->first()->fee_bps);
    }

    public function test_provider_driver_must_be_configured(): void
    {
        $this->confirmed(User::factory()->admin()->create())
            ->post('/admin/providers', ['name' => 'X', 'code' => 'x', 'driver' => 'App\\Evil'])
            ->assertSessionHasErrors('driver');
    }

    public function test_admin_assigns_providers_to_a_merchant(): void
    {
        $merchant = Merchant::factory()->create();
        $alpha = Provider::factory()->offering([PaymentMethod::Qrph, PaymentMethod::Card])->create();
        $beta = Provider::factory()->offering([PaymentMethod::Qrph, PaymentMethod::Gcash])->create();
        $admin = User::factory()->admin()->create();

        $this->confirmed($admin)
            ->get("/admin/merchants/{$merchant->id}/payment-methods")
            ->assertOk()
            ->assertSee($alpha->name)
            ->assertSee($beta->name);

        $this->confirmed($admin)
            ->put("/admin/merchants/{$merchant->id}/payment-methods", ['assignments' => [
                'QRPH' => $alpha->paymentMethods()->where('payment_method', 'QRPH')->value('id'),
                'GCASH' => $beta->paymentMethods()->where('payment_method', 'GCASH')->value('id'),
                'CARD' => '',
            ]])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($alpha->id, $merchant->providerFor(PaymentMethod::Qrph)->provider_id);
        $this->assertSame($beta->id, $merchant->providerFor(PaymentMethod::Gcash)->provider_id);
        $this->assertNull($merchant->providerFor(PaymentMethod::Card));

        // Leaving a method out unassigns it.
        $this->confirmed($admin)
            ->put("/admin/merchants/{$merchant->id}/payment-methods", ['assignments' => [
                'QRPH' => $beta->paymentMethods()->where('payment_method', 'QRPH')->value('id'),
            ]]);

        $this->assertSame($beta->id, $merchant->providerFor(PaymentMethod::Qrph)->provider_id);
        $this->assertNull($merchant->providerFor(PaymentMethod::Gcash));
    }

    public function test_cannot_assign_a_provider_that_does_not_offer_the_method(): void
    {
        $merchant = Merchant::factory()->create();
        $provider = Provider::factory()->offering([PaymentMethod::Card])->create();

        $this->confirmed(User::factory()->admin()->create())
            ->put("/admin/merchants/{$merchant->id}/payment-methods", ['assignments' => [
                'GCASH' => $provider->paymentMethods()->sole()->id,
            ]])
            ->assertSessionHasErrors('assignments.GCASH');

        $this->assertDatabaseCount('merchant_payment_methods', 0);
    }

    public function test_cannot_assign_an_inactive_provider(): void
    {
        $merchant = Merchant::factory()->create();
        $provider = Provider::factory()->inactive()->offering([PaymentMethod::Gcash])->create();

        $this->confirmed(User::factory()->admin()->create())
            ->put("/admin/merchants/{$merchant->id}/payment-methods", ['assignments' => [
                'GCASH' => $provider->paymentMethods()->sole()->id,
            ]])
            ->assertSessionHasErrors('assignments.GCASH');
    }

    public function test_admin_is_sent_to_the_admin_area_without_getting_a_merchant(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/dashboard')->assertRedirect(route('admin.home'));
        $this->actingAs($admin)->get('/dashboard/payments')->assertRedirect(route('admin.home'));

        $this->assertNull($admin->fresh()->merchant);
    }

    public function test_user_without_a_role_cannot_use_the_dashboard(): void
    {
        $user = User::factory()->create();
        $user->syncRoles([]);

        $this->actingAs($user)->get('/dashboard')->assertForbidden();
        $this->assertNull($user->fresh()->merchant);
    }

    public function test_registration_grants_the_merchant_role(): void
    {
        $this->post('/register', [
            'name' => 'New Merchant',
            'email' => 'new@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertTrue(User::where('email', 'new@example.com')->sole()->hasRole('merchant'));
    }

    public function test_grant_admin_command(): void
    {
        $user = User::factory()->create(['email' => 'ops@example.com']);

        $this->artisan('user:grant-admin', ['email' => 'ops@example.com'])->assertSuccessful();
        $this->artisan('user:grant-admin', ['email' => 'missing@example.com'])->assertFailed();

        $this->assertTrue($user->fresh()->hasRole('admin'));
    }
}
