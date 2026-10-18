<?php

namespace App\Support;

use App\Models\User;

/**
 * The sidebar. Each item is visible under the same rule as the route it
 * links to, so a hidden link always means a forbidden page.
 */
final class Navigation
{
    /**
     * @return list<array{label: string, items: list<array{label: string, route: string, icon: string, active: string}>}>
     */
    public static function for(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $sections = [];

        if ($user->hasRole(Permissions::MERCHANT)) {
            $sections[] = ['label' => 'Merchant', 'items' => [
                self::item('Dashboard', 'dashboard', 'home', 'dashboard'),
                self::item('Payments', 'dashboard.payments.index', 'banknotes', 'dashboard.payments.*'),
                self::item('Payment methods', 'dashboard.payment-methods.index', 'credit-card', 'dashboard.payment-methods.*'),
                self::item('API keys', 'dashboard.api-keys.index', 'key', 'dashboard.api-keys.*'),
                self::item('Webhooks', 'dashboard.webhooks.edit', 'bolt', 'dashboard.webhooks.*'),
                self::item('API docs', 'dashboard.docs', 'book-open', 'dashboard.docs*'),
            ]];
        }

        if ($user->isStaff()) {
            $sections[] = ['label' => 'Administration', 'items' => array_values(array_filter([
                self::item('Overview', 'admin.home', 'chart-bar', 'admin.home'),
                $user->can('users.view') ? self::item('Users', 'admin.users.index', 'users', 'admin.users.*') : null,
                $user->can('roles.view') ? self::item('Roles & permissions', 'admin.roles.index', 'shield-check', 'admin.roles.*') : null,
                $user->canAny(self::MERCHANT_LIST) ? self::item('Merchants', 'admin.merchants.index', 'building-storefront', 'admin.merchants.*') : null,
                $user->canAny(self::PROVIDER_LIST) ? self::item('Providers', 'admin.providers.index', 'server-stack', 'admin.providers.*') : null,
            ]))];
        }

        $sections[] = ['label' => 'Account', 'items' => [
            self::item('Profile', 'profile.edit', 'user-circle', 'profile.edit'),
            self::item('Security', 'profile.security', 'lock-closed', 'profile.security'),
        ]];

        return $sections;
    }

    /** Either permission opens the merchant list. */
    public const MERCHANT_LIST = ['merchants.view', 'merchant-providers.assign'];

    /** Either permission opens the provider list. */
    public const PROVIDER_LIST = ['providers.view', 'providers.manage'];

    /**
     * @return array{label: string, route: string, icon: string, active: string}
     */
    private static function item(string $label, string $route, string $icon, string $active): array
    {
        return compact('label', 'route', 'icon', 'active');
    }
}
