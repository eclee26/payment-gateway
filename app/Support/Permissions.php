<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * The catalogue of admin-area permissions. Roles (other than the system
 * roles) are built from these by super-admins in the role editor.
 */
final class Permissions
{
    public const SUPER_ADMIN = 'super-admin';

    public const MERCHANT = 'merchant';

    /** Roles that cannot be edited or deleted from the UI. */
    public const SYSTEM_ROLES = [self::SUPER_ADMIN, self::MERCHANT];

    /** @var array<string, array<string, string>> group => [permission => label] */
    public const GROUPS = [
        'Users' => [
            'users.view' => 'View users',
            'users.create' => 'Create users',
            'users.update' => 'Update users',
            'users.delete' => 'Delete users',
        ],
        'Roles & permissions' => [
            'roles.view' => 'View roles',
            'roles.create' => 'Create roles',
            'roles.update' => 'Update roles',
            'roles.delete' => 'Delete roles',
        ],
        'Merchants' => [
            'merchants.view' => 'View merchants',
            'merchant-providers.assign' => 'Assign providers to merchants',
        ],
        'Providers' => [
            'providers.view' => 'View providers',
            'providers.manage' => 'Create and edit providers',
        ],
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::GROUPS)));
    }

    /**
     * Removing this user's super-admin role (or the user) would leave nobody
     * able to manage roles.
     */
    public static function isLastSuperAdmin(User $user): bool
    {
        return $user->isSuperAdmin()
            && User::role(self::SUPER_ADMIN)->whereKeyNot($user->getKey())->doesntExist();
    }

    /**
     * Roles the actor may give to a user: never super-admin unless the actor
     * is one, and only roles whose permissions the actor already holds.
     *
     * @return Collection<int, Role>
     */
    public static function assignableRoles(User $actor): Collection
    {
        return Role::with('permissions')->orderBy('name')->get()
            ->filter(fn (Role $role) => self::canGrantRole($actor, $role))
            ->values();
    }

    public static function canGrantRole(User $actor, Role $role): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        return $role->name !== self::SUPER_ADMIN
            && self::holdsAll($actor, $role->permissions->pluck('name')->all());
    }

    /**
     * Whether the actor may edit or delete this user. Non-super-admins can't
     * touch super-admins or anyone whose role carries permissions they lack.
     */
    public static function canManageUser(User $actor, User $target): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        return ! $target->isSuperAdmin()
            && self::holdsAll($actor, $target->getPermissionsViaRoles()->pluck('name')->all());
    }

    /**
     * @param  list<string>  $permissions
     */
    public static function holdsAll(User $actor, array $permissions): bool
    {
        return $actor->isSuperAdmin()
            || array_diff($permissions, $actor->getAllPermissions()->pluck('name')->all()) === [];
    }

    /**
     * Staff can enter the admin area: super-admins, or anyone holding at
     * least one admin permission.
     */
    public static function isStaff(User $user): bool
    {
        return $user->hasRole(self::SUPER_ADMIN) || $user->hasAnyPermission(self::all());
    }
}
