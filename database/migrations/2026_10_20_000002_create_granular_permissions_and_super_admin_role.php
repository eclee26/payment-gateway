<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The permission list is copied here rather than read from
 * App\Support\Permissions, so later catalogue changes don't rewrite
 * history.
 */
return new class extends Migration
{
    private const NEW_PERMISSIONS = [
        'users.view', 'users.create', 'users.update', 'users.delete',
        'roles.view', 'roles.create', 'roles.update', 'roles.delete',
        'merchants.view', 'providers.view',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::NEW_PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // super-admin gets everything through Gate::before, not stored permissions.
        Role::findOrCreate('super-admin', 'web');

        // Existing admins keep what they had and gain the matching view and
        // user-management permissions. Role management stays with super-admins.
        Role::findOrCreate('admin', 'web')->givePermissionTo([
            'users.view', 'users.create', 'users.update', 'users.delete',
            'merchants.view', 'providers.view',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Role::where('name', 'super-admin')->delete();
        Permission::whereIn('name', self::NEW_PERMISSIONS)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
