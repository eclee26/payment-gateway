<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use App\Services\Processor\ProviderCallback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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
        $payment = $request->user()->merchant->payments()->with(['refunds', 'provider'])->findOrFail($payment);

        Gate::authorize('view', $payment);

        return view('dashboard.payments.show', ['payment' => $payment]);
    }

    /**
     * Development only: act as the simulated provider and report that the
     * customer completed (or failed) a pending payment. Goes through the
     * same callback handling as a real provider notification.
     */
    public function simulate(Request $request, string $payment, PaymentService $payments): RedirectResponse
    {
        abort_unless(config('gateway.simulator_enabled'), 404);

        $payment = $request->user()->merchant->payments()->with('provider')->findOrFail($payment);

        Gate::authorize('view', $payment);

        $validated = $request->validate(['outcome' => ['required', Rule::in(['succeeded', 'failed'])]]);

        if ($payment->provider?->driver !== 'simulated' || $payment->status !== PaymentStatus::RequiresAction) {
            return back()->with('status', 'Only pending payments with a simulated provider can be simulated.');
        }

        $payments->handleProviderCallback($payment->provider, new ProviderCallback(
            $payment->provider_reference,
            $validated['outcome'] === 'succeeded',
            $validated['outcome'] === 'failed' ? 'simulated_failure' : null,
        ));

        return redirect()->route('dashboard.payments.show', $payment)->with('status', 'Simulated provider callback sent.');
    }
}
