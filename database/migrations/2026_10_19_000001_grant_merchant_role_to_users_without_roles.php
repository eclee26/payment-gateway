<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The first role backfill only covered users who already had a merchant.
 * Users who registered but never opened the dashboard (where their
 * merchant is created) were left without a role and locked out by the
 * role:merchant middleware. Give every role-less user the merchant role.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $merchant = Role::findOrCreate('merchant', 'web');
        $userModel = 'App\\Models\\User';

        $userIds = DB::table('users')
            ->whereNotExists(fn ($query) => $query->from('model_has_roles')
                ->whereColumn('model_has_roles.model_id', 'users.id')
                ->where('model_has_roles.model_type', $userModel))
            ->pluck('id');

        DB::table('model_has_roles')->insertOrIgnore($userIds->map(fn ($id) => [
            'role_id' => $merchant->id,
            'model_type' => $userModel,
            'model_id' => $id,
        ])->all());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations. Roles granted here cannot be told apart from
     * ones granted later, so they are left in place.
     */
    public function down(): void {}
};
