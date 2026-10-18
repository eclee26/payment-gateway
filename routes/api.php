<?php

use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\RefundController;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\RequireJson;
use App\Http\Middleware\VerifyHmacSignature;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware([VerifyHmacSignature::class, 'throttle:api', RequireJson::class, EnsureIdempotency::class])
    ->name('api.')
    ->group(function () {
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::post('/payments/{payment}/capture', [PaymentController::class, 'capture'])->name('payments.capture');
        Route::post('/payments/{payment}/cancel', [PaymentController::class, 'cancel'])->name('payments.cancel');
        Route::get('/payments/{payment}/refunds', [RefundController::class, 'index'])->name('refunds.index');
        Route::post('/payments/{payment}/refunds', [RefundController::class, 'store'])->name('refunds.store');
    });
