<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private function confirmed(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function staff(array $permissions, string $role = 'Role editor'): User
    {
        Role::findOrCreate($role, 'web')->syncPermissions($permissions);

        return tap(User::factory()->create(), fn (User $user) => $user->syncRoles([$role]));
    }

    public function test_super_admin_creates_a_custom_role(): void
    {
        $this->confirmed(User::factory()->superAdmin()->create())
            ->post('/admin/roles', [
                'name' => 'Custom-Role1',
                'permissions' => ['users.view', 'users.create', 'users.update', 'users.delete'],
            ])
            ->assertRedirect('/admin/roles');

        $role = Role::findByName('Custom-Role1');
        $this->assertEqualsCanonicalizing(
            ['users.view', 'users.create', 'users.update', 'users.delete'],
            $role->permissions->pluck('name')->all(),
        );
    }

    public function test_custom_role_only_reaches_what_it_grants(): void
    {
        $user = $this->staff(['users.view'], 'Custom-Role1');

        $this->confirmed($user)->get('/admin')->assertOk();
        $this->confirmed($user)->get('/admin/users')->assertOk();

        $this->confirmed($user)->get('/admin/users/create')->assertForbidden();
        $this->confirmed($user)->get('/admin/providers')->assertForbidden();
        $this->confirmed($user)->get('/admin/merchants')->assertForbidden();
        $this->confirmed($user)->get('/admin/roles')->assertForbidden();
    }

    public function test_role_actions_need_their_permissions(): void
    {
        $role = Role::create(['name' => 'Target', 'guard_name' => 'web']);
        $viewer = $this->staff(['roles.view']);

        $this->confirmed($viewer)->get('/admin/roles')->assertOk();
        $this->confirmed($viewer)->get('/admin/roles/create')->assertForbidden();
        $this->confirmed($viewer)->post('/admin/roles', ['name' => 'X'])->assertForbidden();
        $this->confirmed($viewer)->get("/admin/roles/{$role->id}/edit")->assertForbidden();
        $this->confirmed($viewer)->put("/admin/roles/{$role->id}", ['name' => 'X'])->assertForbidden();
        $this->confirmed($viewer)->delete("/admin/roles/{$role->id}")->assertForbidden();
    }

    public function test_update_and_delete_a_role(): void
    {
        $role = Role::create(['name' => 'Support', 'guard_name' => 'web']);
        $actor = User::factory()->superAdmin()->create();

        $this->confirmed($actor)->put("/admin/roles/{$role->id}", ['name' => 'Support team', 'permissions' => ['merchants.view']])
            ->assertRedirect('/admin/roles');
        $this->assertSame(['merchants.view'], $role->fresh()->permissions->pluck('name')->all());
        $this->assertSame('Support team', $role->fresh()->name);

        $this->confirmed($actor)->delete("/admin/roles/{$role->id}")->assertRedirect('/admin/roles');
        $this->assertModelMissing($role);
    }

    public function test_system_roles_are_immutable(): void
    {
        $actor = User::factory()->superAdmin()->create();

        foreach (['super-admin', 'merchant'] as $name) {
            $role = Role::findByName($name);

            $this->confirmed($actor)->get("/admin/roles/{$role->id}/edit")->assertForbidden();
            $this->confirmed($actor)->put("/admin/roles/{$role->id}", ['name' => 'renamed', 'permissions' => []])->assertForbidden();
            $this->confirmed($actor)->delete("/admin/roles/{$role->id}")->assertForbidden();
            $this->assertModelExists($role);
        }

        $this->confirmed($actor)->post('/admin/roles', ['name' => 'super-admin'])->assertSessionHasErrors('name');
    }

    public function test_role_in_use_cannot_be_deleted(): void
    {
        $this->staff([], 'Busy');

        $this->confirmed(User::factory()->superAdmin()->create())
            ->delete('/admin/roles/'.Role::findByName('Busy')->id)
            ->assertSessionHas('error');

        $this->assertNotNull(Role::findByName('Busy'));
    }

    public function test_cannot_grant_permissions_you_do_not_hold(): void
    {
        $actor = $this->staff(['roles.view', 'roles.create', 'users.view']);

        $this->confirmed($actor)->post('/admin/roles', ['name' => 'Escalate', 'permissions' => ['users.view', 'providers.manage']])
            ->assertSessionHasErrors('permissions');
        $this->assertDatabaseMissing('roles', ['name' => 'Escalate']);

        $this->confirmed($actor)->post('/admin/roles', ['name' => 'Fine', 'permissions' => ['users.view']])
            ->assertSessionHasNoErrors();
    }

    public function test_cannot_edit_a_role_with_permissions_you_lack(): void
    {
        $actor = $this->staff(['roles.view', 'roles.update', 'roles.delete']);
        $admin = Role::findByName('admin');

        $this->confirmed($actor)->get("/admin/roles/{$admin->id}/edit")->assertForbidden();
        $this->confirmed($actor)->put("/admin/roles/{$admin->id}", ['name' => 'admin', 'permissions' => []])->assertForbidden();
        $this->confirmed($actor)->delete("/admin/roles/{$admin->id}")->assertForbidden();
    }

    public function test_unknown_permissions_are_rejected(): void
    {
        $this->confirmed(User::factory()->superAdmin()->create())
            ->post('/admin/roles', ['name' => 'Odd', 'permissions' => ['payments.steal']])
            ->assertSessionHasErrors('permissions.0');
    }

    public function test_permission_changes_apply_immediately(): void
    {
        $user = $this->staff(['users.view'], 'Custom-Role1');
        $this->confirmed($user)->get('/admin/providers')->assertForbidden();

        Role::findByName('Custom-Role1')->givePermissionTo('providers.view');

        $this->confirmed($user->fresh())->get('/admin/providers')->assertOk();
    }
}
