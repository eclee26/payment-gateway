<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MerchantController;
use App\Http\Controllers\Admin\MerchantPaymentMethodController;
use App\Http\Controllers\Admin\ProviderController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Dashboard\ApiKeyController;
use App\Http\Controllers\Dashboard\DocsController;
use App\Http\Controllers\Dashboard\PaymentController;
use App\Http\Controllers\Dashboard\PaymentMethodController;
use App\Http\Controllers\Dashboard\WebhookController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\EnsureStaffAccess;
use App\Http\Middleware\EnsureUserHasMerchant;
use App\Http\Middleware\RedirectAdminsToAdminArea;
use App\Support\Navigation;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'verified', RedirectAdminsToAdminArea::class, 'role:merchant', EnsureUserHasMerchant::class])->group(function () {
    Route::get('/dashboard', [PaymentController::class, 'dashboard'])->name('dashboard');

    Route::prefix('dashboard')->name('dashboard.')->group(function () {
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::post('/payments/{payment}/simulate', [PaymentController::class, 'simulate'])->name('payments.simulate');

        Route::get('/payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');

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

// The admin area. Open to any staff role; each route checks its own
// permission, and the sidebar (App\Support\Navigation) uses the same rules.
// Changing which bank processes a merchant's money, or who may do so, is
// sensitive, so the password must have been confirmed recently.
Route::middleware(['auth', 'verified', EnsureStaffAccess::class, 'password.confirm'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('home');

    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->middleware('permission:users.create')->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.create')->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->middleware('permission:users.update')->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.update')->name('users.update');
    Route::post('/users/{user}/reset-two-factor', [UserController::class, 'resetTwoFactor'])->middleware('permission:users.update')->name('users.reset-two-factor');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->name('users.destroy');

    Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('roles.index');
    Route::get('/roles/create', [RoleController::class, 'create'])->middleware('permission:roles.create')->name('roles.create');
    Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('roles.store');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:roles.update')->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.update')->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->name('roles.destroy');

    Route::get('/merchants', [MerchantController::class, 'index'])->middleware('permission:'.implode('|', Navigation::MERCHANT_LIST))->name('merchants.index');
    Route::get('/merchants/{merchant}', [MerchantController::class, 'show'])->middleware('permission:merchants.view')->name('merchants.show');

    Route::middleware('permission:merchant-providers.assign')->group(function () {
        Route::get('/merchants/{merchant}/payment-methods', [MerchantPaymentMethodController::class, 'edit'])->name('merchants.payment-methods.edit');
        Route::put('/merchants/{merchant}/payment-methods', [MerchantPaymentMethodController::class, 'update'])->name('merchants.payment-methods.update');
    });

    Route::get('/providers', [ProviderController::class, 'index'])->middleware('permission:'.implode('|', Navigation::PROVIDER_LIST))->name('providers.index');

    Route::middleware('permission:providers.manage')->group(function () {
        Route::get('/providers/create', [ProviderController::class, 'create'])->name('providers.create');
        Route::post('/providers', [ProviderController::class, 'store'])->name('providers.store');
        Route::get('/providers/{provider}/edit', [ProviderController::class, 'edit'])->name('providers.edit');
        Route::put('/providers/{provider}', [ProviderController::class, 'update'])->name('providers.update');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
