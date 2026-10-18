<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProviderRequest;
use App\Models\Provider;
use App\Services\Processor\ProcessorManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProviderController extends Controller
{
    /**
     * All providers with the methods they offer.
     */
    public function index(): View
    {
        return view('admin.providers.index', [
            'providers' => Provider::with('paymentMethods')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Provider(['driver' => ProcessorManager::drivers()[0] ?? null, 'is_active' => true]));
    }

    public function store(SaveProviderRequest $request): RedirectResponse
    {
        $provider = DB::transaction(function () use ($request) {
            $provider = Provider::create($request->safe()->only(['code', 'name', 'driver', 'is_active']));
            $this->syncMethods($provider, $request->methods());

            return $provider;
        });

        return redirect()->route('admin.providers.index')->with('status', "Provider {$provider->name} created.");
    }

    public function edit(Provider $provider): View
    {
        return $this->form($provider->load('paymentMethods'));
    }

    public function update(SaveProviderRequest $request, Provider $provider): RedirectResponse
    {
        DB::transaction(function () use ($request, $provider) {
            $provider->update($request->safe()->only(['code', 'name', 'driver', 'is_active']));
            $this->syncMethods($provider, $request->methods());
        });

        return redirect()->route('admin.providers.index')->with('status', "Provider {$provider->name} updated.");
    }

    private function form(Provider $provider): View
    {
        return view('admin.providers.form', [
            'provider' => $provider,
            'drivers' => ProcessorManager::drivers(),
            'methods' => PaymentMethod::cases(),
            'pricing' => $provider->paymentMethods->keyBy(fn ($row) => $row->payment_method->value),
        ]);
    }

    /**
     * Rows are deactivated rather than deleted, because merchant assignments
     * and past payments may still reference them.
     *
     * @param  array<string, array{is_active: bool, fee_bps: int, fee_fixed: int}>  $methods
     */
    private function syncMethods(Provider $provider, array $methods): void
    {
        $existing = $provider->paymentMethods()->get()->keyBy(fn ($row) => $row->payment_method->value);

        foreach (PaymentMethod::cases() as $method) {
            $row = $methods[$method->value] ?? ['is_active' => false];

            if ($current = $existing->get($method->value)) {
                $current->update($row);
            } elseif ($row['is_active']) {
                $provider->paymentMethods()->create(['payment_method' => $method->value] + $row);
            }
        }
    }
}
