<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MerchantController extends Controller
{
    /**
     * All merchants with their current provider assignments.
     */
    public function index(Request $request): View
    {
        $search = $request->validate(['search' => ['nullable', 'string', 'max:255']])['search'] ?? null;

        return view('admin.merchants.index', [
            'merchants' => Merchant::with(['user', 'paymentMethodAssignments.providerPaymentMethod.provider'])
                ->withCount('payments')
                ->when($search, fn ($query) => $query->where(fn ($query) => $query
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhereHas('user', fn ($query) => $query->where('email', 'like', '%'.$search.'%'))))
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function show(Merchant $merchant): View
    {
        $merchant->load(['user', 'paymentMethodAssignments.providerPaymentMethod.provider'])
            ->loadCount(['payments', 'apiKeys' => fn ($query) => $query->whereNull('revoked_at')]);

        // Volume per currency over captured payments.
        $volume = $merchant->payments()
            ->whereIn('status', [PaymentStatus::Succeeded, PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded])
            ->selectRaw('currency, sum(amount_captured) as captured, count(*) as payments')
            ->groupBy('currency')
            ->get();

        return view('admin.merchants.show', [
            'merchant' => $merchant,
            'volume' => $volume,
            'payments' => $merchant->payments()->with('provider')->latest()->latest('id')->limit(10)->get(),
        ]);
    }
}
