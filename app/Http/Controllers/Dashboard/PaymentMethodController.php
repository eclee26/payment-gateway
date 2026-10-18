<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentMethodController extends Controller
{
    /**
     * The merchant's payment methods and the provider handling each one.
     * Read-only: assignments are made by an admin.
     */
    public function index(Request $request): View
    {
        $merchant = $request->user()->merchant;

        return view('dashboard.payment-methods.index', [
            'methods' => collect(PaymentMethod::cases())->mapWithKeys(fn (PaymentMethod $method) => [
                $method->value => ['method' => $method, 'pricing' => $merchant->providerFor($method)],
            ]),
        ]);
    }
}
