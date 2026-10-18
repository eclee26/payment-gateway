<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\Payment;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    /**
     * The merchant authenticated by VerifyHmacSignature.
     */
    protected function merchant(Request $request): Merchant
    {
        return $request->attributes->get('merchant');
    }

    /**
     * Find one of the merchant's own payments. Other merchants' payments
     * are indistinguishable from non-existent ones (404).
     */
    protected function findPayment(Request $request, string $id): Payment
    {
        return $this->merchant($request)->payments()->findOrFail($id);
    }
}
