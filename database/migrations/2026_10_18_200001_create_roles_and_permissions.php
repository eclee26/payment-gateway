<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles and permissions are reference data the app cannot run without, so
 * they live in a migration rather than a seeder.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect(['providers.manage', 'merchant-providers.assign'])
            ->map(fn (string $name) => Permission::findOrCreate($name, 'web'));

        Role::findOrCreate('admin', 'web')->syncPermissions($permissions);
        $merchant = Role::findOrCreate('merchant', 'web');

        // Every existing user operating a merchant account keeps dashboard access.
        $userIds = DB::table('merchants')->pluck('user_id');
        DB::table('model_has_roles')->insertOrIgnore($userIds->map(fn ($id) => [
            'role_id' => $merchant->id,
            'model_type' => 'App\\Models\\User',
            'model_id' => $id,
        ])->all());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Role::whereIn('name', ['admin', 'merchant'])->delete();
        Permission::whereIn('name', ['providers.manage', 'merchant-providers.assign'])->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
