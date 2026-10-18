<?php

namespace Tests\Feature\Admin;

use App\Models\Merchant;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function confirmed(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
    }

    /**
     * A user whose only role grants exactly these permissions.
     *
     * @param  list<string>  $permissions
     */
    private function staff(array $permissions, string $role = 'Custom-Role1'): User
    {
        Role::findOrCreate($role, 'web')->syncPermissions($permissions);

        return tap(User::factory()->create(), fn (User $user) => $user->syncRoles([$role]));
    }

    private function newUser(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'New Person',
            'email' => 'new@example.com',
            'password' => 'Secret-password-1',
            'password_confirmation' => 'Secret-password-1',
            'role' => 'merchant',
        ];
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function permissionChecks(): array
    {
        return [
            'list' => ['users.view', 'GET', '/admin/users'],
            'create form' => ['users.create', 'GET', '/admin/users/create'],
            'store' => ['users.create', 'POST', '/admin/users'],
            'edit form' => ['users.update', 'GET', '/admin/users/{target}/edit'],
            'update' => ['users.update', 'PUT', '/admin/users/{target}'],
            'reset 2fa' => ['users.update', 'POST', '/admin/users/{target}/reset-two-factor'],
            'delete' => ['users.delete', 'DELETE', '/admin/users/{target}'],
        ];
    }

    #[DataProvider('permissionChecks')]
    public function test_each_action_needs_its_own_permission(string $permission, string $method, string $uri): void
    {
        $target = User::factory()->create();
        $uri = str_replace('{target}', (string) $target->id, $uri);
        $data = $method === 'GET' ? [] : $this->newUser(['email' => 'other@example.com']);

        // Another user permission alone (users.view keeps the admin area open) is not enough.
        $other = $permission === 'users.view' ? 'providers.view' : 'users.view';
        $this->confirmed($this->staff([$other], 'Without'))->call($method, $uri, $data)->assertForbidden();

        $response = $this->confirmed($this->staff([$permission, 'users.view'], 'With'))->call($method, $uri, $data);
        $this->assertNotSame(403, $response->getStatusCode());
        $response->assertSessionHasNoErrors();
    }

    public function test_merchants_cannot_manage_users(): void
    {
        $this->confirmed(Merchant::factory()->create()->user)->get('/admin/users')->assertForbidden();
    }

    public function test_user_list_can_be_searched_and_filtered(): void
    {
        $actor = $this->staff(['users.view']);
        User::factory()->create(['name' => 'Findable Person', 'email' => 'findme@example.com']);
        User::factory()->create(['name' => 'Hidden Person']);

        $this->confirmed($actor)->get('/admin/users?search=findme')
            ->assertOk()->assertSee('Findable Person')->assertDontSee('Hidden Person');

        $this->confirmed($actor)->get('/admin/users?role=Custom-Role1')
            ->assertOk()->assertSee($actor->email)->assertDontSee('Hidden Person');
    }

    public function test_create_user_with_custom_role(): void
    {
        $this->staff([]); // creates Custom-Role1 with no permissions
        $actor = User::factory()->superAdmin()->create();

        $this->confirmed($actor)->post('/admin/users', $this->newUser(['role' => 'Custom-Role1']))
            ->assertRedirect('/admin/users');

        $user = User::where('email', 'new@example.com')->sole();
        $this->assertTrue($user->hasRole('Custom-Role1'));
        $this->assertSame(['Custom-Role1'], $user->getRoleNames()->all());
        // New users set up TOTP themselves at first sign-in.
        $this->assertFalse($user->hasTwoFactorEnabled());
    }

    public function test_update_user_and_change_role(): void
    {
        $actor = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        $this->confirmed($actor)->put("/admin/users/{$target->id}", [
            'name' => 'Renamed',
            'email' => $target->email,
            'role' => 'admin',
        ])->assertRedirect('/admin/users');

        $target->refresh();
        $this->assertSame('Renamed', $target->name);
        $this->assertSame(['admin'], $target->getRoleNames()->all());
    }

    public function test_cannot_assign_super_admin_unless_super_admin(): void
    {
        $actor = $this->staff(['users.view', 'users.create']);

        $this->confirmed($actor)->post('/admin/users', $this->newUser(['role' => 'super-admin']))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
    }

    public function test_cannot_assign_a_role_with_permissions_the_actor_lacks(): void
    {
        $actor = $this->staff(['users.view', 'users.create']);

        // admin holds providers.* and merchants.* which the actor lacks.
        $this->confirmed($actor)->post('/admin/users', $this->newUser(['role' => 'admin']))
            ->assertSessionHasErrors('role');

        $this->confirmed($actor)->get('/admin/users/create')->assertOk()->assertDontSee('value="admin"', false);
    }

    public function test_cannot_edit_users_more_powerful_than_yourself(): void
    {
        $actor = $this->staff(['users.view', 'users.update', 'users.delete']);
        $admin = User::factory()->admin()->create();
        $super = User::factory()->superAdmin()->create();

        foreach ([$admin, $super] as $target) {
            $this->confirmed($actor)->get("/admin/users/{$target->id}/edit")->assertForbidden();
            $this->confirmed($actor)->put("/admin/users/{$target->id}", ['name' => 'x', 'email' => $target->email, 'role' => 'merchant'])->assertForbidden();
            $this->confirmed($actor)->post("/admin/users/{$target->id}/reset-two-factor")->assertForbidden();
            $this->confirmed($actor)->delete("/admin/users/{$target->id}")->assertForbidden();
        }

        $this->assertTrue($super->fresh()->hasRole('super-admin'));
    }

    public function test_cannot_change_own_role(): void
    {
        $actor = $this->staff(['users.view', 'users.update']);

        $this->confirmed($actor)->put("/admin/users/{$actor->id}", [
            'name' => 'Me',
            'email' => $actor->email,
            'role' => 'merchant',
        ])->assertRedirect('/admin/users');

        $this->assertSame(['Custom-Role1'], $actor->fresh()->getRoleNames()->all());
    }

    public function test_cannot_delete_self(): void
    {
        $actor = User::factory()->superAdmin()->create();
        User::factory()->superAdmin()->create();

        $this->confirmed($actor)->delete("/admin/users/{$actor->id}")->assertSessionHas('error');

        $this->assertModelExists($actor);
    }

    public function test_last_super_admin_cannot_be_demoted(): void
    {
        $only = User::factory()->superAdmin()->create();
        $other = User::factory()->superAdmin()->create();

        // Two super-admins: one may demote the other.
        $this->confirmed($only)->put("/admin/users/{$other->id}", ['name' => 'x', 'email' => $other->email, 'role' => 'admin'])
            ->assertSessionHasNoErrors();

        // Now $only is the last one and cannot be demoted by anybody.
        $this->assertTrue($only->fresh()->isSuperAdmin());
        $this->confirmed($only)->delete('/profile', ['password' => 'password'])->assertSessionHasErrors('password', null, 'userDeletion');
        $this->assertModelExists($only);
    }

    public function test_cannot_delete_a_merchant_user_with_payments(): void
    {
        $merchant = Merchant::factory()->create();
        Payment::factory()->for($merchant)->create();

        $this->confirmed(User::factory()->superAdmin()->create())
            ->delete("/admin/users/{$merchant->user->id}")
            ->assertSessionHas('error');

        $this->assertModelExists($merchant->user);
    }

    public function test_delete_user(): void
    {
        $target = User::factory()->create();

        $this->confirmed($this->staff(['users.view', 'users.delete']))
            ->delete("/admin/users/{$target->id}")
            ->assertRedirect('/admin/users');

        $this->assertModelMissing($target);
    }

    public function test_reset_two_factor_forces_setup_again(): void
    {
        $target = User::factory()->create();

        $this->confirmed($this->staff(['users.view', 'users.update']))
            ->post("/admin/users/{$target->id}/reset-two-factor")
            ->assertSessionHas('status');

        $target->refresh();
        $this->assertFalse($target->hasTwoFactorEnabled());
        $this->assertNull($target->two_factor_secret);
        $this->actingAs($target)->get('/profile')->assertRedirect(route('two-factor.setup'));
    }
}
