<?php

use App\Models\IdempotencyKey;
use App\Models\User;
use App\Services\PaymentService;
use App\Support\Permissions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('user:grant-admin {email}', function (string $email) {
    $user = User::where('email', $email)->first();

    if ($user === null) {
        $this->error("No user with email [{$email}].");

        return 1;
    }

    $user->assignRole('admin');
    $this->info("{$user->email} is now an admin.");

    return 0;
})->purpose('Give a user the admin role (manage providers and merchant assignments)');

Artisan::command('user:grant-super-admin {email}', function (string $email) {
    $user = User::where('email', $email)->first();

    if ($user === null) {
        $this->error("No user with email [{$email}].");

        return 1;
    }

    $user->assignRole(Permissions::SUPER_ADMIN);
    $this->info("{$user->email} is now a super-admin.");

    return 0;
})->purpose('Give a user the super-admin role (every permission, including role management)');

Artisan::command('payments:expire-pending', function (PaymentService $payments) {
    $this->info($payments->expirePending().' pending payment(s) expired.');
})->purpose('Cancel requires_action payments past their expires_at');

Schedule::command('model:prune', ['--model' => [IdempotencyKey::class]])->hourly();

Schedule::command('payments:expire-pending')->everyMinute()->withoutOverlapping();
