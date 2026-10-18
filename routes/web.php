<?php

use App\Http\Controllers\Dashboard\ApiKeyController;
use App\Http\Controllers\Dashboard\DocsController;
use App\Http\Controllers\Dashboard\PaymentController;
use App\Http\Controllers\Dashboard\WebhookController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\EnsureUserHasMerchant;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'verified', EnsureUserHasMerchant::class])->group(function () {
    Route::get('/dashboard', [PaymentController::class, 'dashboard'])->name('dashboard');

    Route::prefix('dashboard')->name('dashboard.')->group(function () {
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');

        Route::get('/docs', [DocsController::class, 'show'])->name('docs');
        Route::get('/docs/postman', [DocsController::class, 'postman'])->name('docs.postman');

        // Credential pages require the password to have been confirmed recently.
        // The GET pages are guarded too, because the confirmation redirect
        // returns to the intended URL with a GET request.
        Route::middleware('password.confirm')->group(function () {
            Route::get('/api-keys', [ApiKeyController::class, 'index'])->name('api-keys.index');
            Route::get('/webhooks', [WebhookController::class, 'edit'])->name('webhooks.edit');
            Route::post('/api-keys', [ApiKeyController::class, 'store'])->name('api-keys.store');
            Route::delete('/api-keys/{apiKey}', [ApiKeyController::class, 'destroy'])->name('api-keys.destroy');
            Route::put('/webhooks', [WebhookController::class, 'update'])->name('webhooks.update');
            Route::post('/webhooks/secret', [WebhookController::class, 'rotateSecret'])->name('webhooks.rotate-secret');
        });
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
