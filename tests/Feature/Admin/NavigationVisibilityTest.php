<?php

namespace Tests\Feature\Admin;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The sidebar shows exactly the areas the user's role can open.
 */
class NavigationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_LINKS = [
        'users' => '/admin/users',
        'roles' => '/admin/roles',
        'merchants' => '/admin/merchants',
        'providers' => '/admin/providers',
    ];

    private const MERCHANT_LINKS = ['/dashboard/payments', '/dashboard/api-keys', '/dashboard/webhooks'];

    /**
     * @param  list<string>  $visible  keys of ADMIN_LINKS
     */
    private function assertSidebar(User $user, string $page, array $visible, bool $merchantLinks): void
    {
        $html = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get($page)
            ->assertOk()
            ->getContent();

        foreach (self::ADMIN_LINKS as $key => $path) {
            in_array($key, $visible, true)
                ? $this->assertStringContainsString('href="'.url($path).'"', $html, "Expected a link to {$path}")
                : $this->assertStringNotContainsString('href="'.url($path).'"', $html, "Unexpected link to {$path}");
        }

        foreach (self::MERCHANT_LINKS as $path) {
            $merchantLinks
                ? $this->assertStringContainsString('href="'.url($path).'"', $html)
                : $this->assertStringNotContainsString('href="'.url($path).'"', $html);
        }
    }

    public function test_merchant_sees_only_the_merchant_portal(): void
    {
        $this->assertSidebar(Merchant::factory()->create()->user, '/dashboard', [], merchantLinks: true);
    }

    public function test_super_admin_sees_every_admin_area(): void
    {
        $this->assertSidebar(User::factory()->superAdmin()->create(), '/admin', array_keys(self::ADMIN_LINKS), merchantLinks: false);
    }

    public function test_admin_sees_everything_but_roles(): void
    {
        $this->assertSidebar(User::factory()->admin()->create(), '/admin', ['users', 'merchants', 'providers'], merchantLinks: false);
    }

    public function test_custom_role_sees_only_its_areas(): void
    {
        Role::create(['name' => 'Custom-Role1', 'guard_name' => 'web'])
            ->syncPermissions(['users.view', 'users.create', 'users.update', 'users.delete']);
        $user = tap(User::factory()->create(), fn (User $user) => $user->syncRoles(['Custom-Role1']));

        $this->assertSidebar($user, '/admin', ['users'], merchantLinks: false);
    }

    public function test_assign_only_role_sees_merchants(): void
    {
        Role::create(['name' => 'Assigner', 'guard_name' => 'web'])->syncPermissions(['merchant-providers.assign']);
        $user = tap(User::factory()->create(), fn (User $user) => $user->syncRoles(['Assigner']));

        $this->assertSidebar($user, '/admin', ['merchants'], merchantLinks: false);
    }

    public function test_staff_land_in_the_admin_area(): void
    {
        Role::create(['name' => 'Custom-Role1', 'guard_name' => 'web'])->syncPermissions(['users.view']);
        $user = tap(User::factory()->create(), fn (User $user) => $user->syncRoles(['Custom-Role1']));

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('admin.home'));
    }

    public function test_role_without_permissions_has_no_admin_access(): void
    {
        Role::create(['name' => 'Empty', 'guard_name' => 'web']);
        $user = tap(User::factory()->create(), fn (User $user) => $user->syncRoles(['Empty']));

        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->get('/admin')->assertForbidden();
    }
}
