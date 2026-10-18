<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PaymentController extends Controller
{
    /**
     * Overview with the merchant's most recent payments.
     */
    public function dashboard(Request $request): View
    {
        $merchant = $request->user()->merchant;

        return view('dashboard', [
            'merchant' => $merchant,
            'payments' => $merchant->payments()->latest()->latest('id')->limit(10)->get(),
            'activeKeys' => $merchant->apiKeys()->whereNull('revoked_at')->count(),
        ]);
    }

    /**
     * All of the merchant's payments.
     */
    public function index(Request $request): View
    {
        return view('dashboard.payments.index', [
            'payments' => $request->user()->merchant->payments()->latest()->latest('id')->paginate(25),
        ]);
    }

    /**
     * A single payment and its refunds.
     */
    public function show(Request $request, string $payment): View
    {
        // Scoped lookup: other merchants' payments are a 404, not a 403.
        $payment = $request->user()->merchant->payments()->with('refunds')->findOrFail($payment);

        Gate::authorize('view', $payment);

        return view('dashboard.payments.show', ['payment' => $payment]);
    }
}
