<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignPaymentMethodsRequest;
use App\Models\Merchant;
use App\Models\ProviderPaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MerchantPaymentMethodController extends Controller
{
    /**
     * One provider select per payment method, listing the active providers
     * that offer it with their pricing.
     */
    public function edit(Merchant $merchant): View
    {
        $options = ProviderPaymentMethod::available()
            ->with('provider')
            ->get()
            ->groupBy(fn ($row) => $row->payment_method->value);

        return view('admin.merchants.payment-methods', [
            'merchant' => $merchant,
            'methods' => PaymentMethod::cases(),
            'options' => $options,
            'current' => $merchant->paymentMethodAssignments()
                ->pluck('provider_payment_method_id', 'payment_method')
                ->mapWithKeys(fn ($id, $method) => [$method instanceof PaymentMethod ? $method->value : $method => $id]),
        ]);
    }

    public function update(AssignPaymentMethodsRequest $request, Merchant $merchant): RedirectResponse
    {
        $assignments = $request->assignments();

        DB::transaction(function () use ($merchant, $assignments) {
            $merchant->paymentMethodAssignments()
                ->whereNotIn('payment_method', array_keys($assignments))
                ->delete();

            foreach ($assignments as $method => $id) {
                $merchant->paymentMethodAssignments()->updateOrCreate(
                    ['payment_method' => $method],
                    ['provider_payment_method_id' => $id],
                );
            }
        });

        return redirect()->route('admin.merchants.payment-methods.edit', $merchant)->with('status', 'Payment methods saved.');
    }
}
