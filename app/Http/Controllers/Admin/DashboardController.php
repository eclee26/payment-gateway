<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\Provider;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    /**
     * Overview for staff. Each card only appears (and is only queried) when
     * the user may open the page it summarises.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $stats = [];

        if ($user->can('users.view')) {
            $stats[] = [
                'label' => 'Users',
                'value' => number_format(User::count()),
                'hint' => number_format(User::whereNull('two_factor_confirmed_at')->count()).' without 2FA yet',
                'route' => 'admin.users.index',
                'icon' => 'users',
            ];
        }

        if ($user->can('roles.view')) {
            $stats[] = [
                'label' => 'Roles',
                'value' => number_format(Role::count()),
                'hint' => 'Including system roles',
                'route' => 'admin.roles.index',
                'icon' => 'shield-check',
            ];
        }

        if ($user->canAny(Navigation::MERCHANT_LIST)) {
            $stats[] = [
                'label' => 'Merchants',
                'value' => number_format(Merchant::count()),
                'hint' => number_format(Merchant::has('paymentMethodAssignments', '=', 0)->count()).' with no payment methods',
                'route' => 'admin.merchants.index',
                'icon' => 'building-storefront',
            ];
        }

        if ($user->canAny(Navigation::PROVIDER_LIST)) {
            $stats[] = [
                'label' => 'Active providers',
                'value' => number_format(Provider::where('is_active', true)->count()),
                'hint' => number_format(Provider::count()).' in total',
                'route' => 'admin.providers.index',
                'icon' => 'server-stack',
            ];
        }

        $recentPayments = $user->can('merchants.view')
            ? Payment::with('merchant')->latest()->latest('id')->limit(8)->get()
            : null;

        $paymentStats = $user->can('merchants.view') ? [
            'today' => Payment::where('created_at', '>=', now()->startOfDay())->count(),
            'succeeded' => Payment::where('created_at', '>=', now()->subDays(30))->where('status', PaymentStatus::Succeeded)->count(),
            'failed' => Payment::where('created_at', '>=', now()->subDays(30))->where('status', PaymentStatus::Failed)->count(),
            'pending' => Payment::where('status', PaymentStatus::RequiresAction)->count(),
        ] : null;

        return view('admin.dashboard', compact('stats', 'recentPayments', 'paymentStats'));
    }
}
