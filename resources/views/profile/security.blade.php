<x-app-layout :title="__('Security')" :subtitle="__('Two-factor authentication protects your account even if your password leaks.')">
    <div class="card divide-y divide-slate-100">
        <div class="grid gap-6 p-6 md:grid-cols-3">
            <header>
                <h2 class="text-base font-semibold text-slate-900">{{ __('Authenticator app') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('Required for every account. You are asked for a code each time you sign in.') }}</p>
            </header>
            <div class="md:col-span-2">
                <div class="flex items-center gap-4">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <x-icon name="device-phone-mobile" class="size-6" />
                    </span>
                    <div>
                        <p class="flex items-center gap-2 text-sm font-medium text-slate-900">
                            {{ __('TOTP authenticator') }}
                            <span class="badge-green">{{ __('Enabled') }}</span>
                        </p>
                        <p class="text-sm text-slate-500">
                            {{ __('Enabled :date', ['date' => $user->two_factor_confirmed_at?->toFormattedDayDateString()]) }}
                        </p>
                    </div>
                </div>
                <p class="mt-4 text-sm text-slate-500">
                    {{ __('Lost your device? Sign in with a recovery code, or ask an administrator to reset your two-factor authentication.') }}
                </p>
            </div>
        </div>

        <div class="grid gap-6 p-6 md:grid-cols-3">
            <header>
                <h2 class="text-base font-semibold text-slate-900">{{ __('Recovery codes') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('One-time codes for when you cannot use your authenticator app.') }}</p>
            </header>
            <div class="md:col-span-2">
                <p class="text-sm text-slate-700">
                    {{ trans_choice(':count recovery code remaining.|:count recovery codes remaining.', count($user->two_factor_recovery_codes ?? []), ['count' => count($user->two_factor_recovery_codes ?? [])]) }}
                </p>
                <form method="POST" action="{{ route('two-factor.recovery-codes.regenerate') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="btn-secondary">
                        <x-icon name="arrow-path" class="size-4" />
                        {{ __('Generate new recovery codes') }}
                    </button>
                    <p class="form-help">{{ __('Your old codes stop working immediately.') }}</p>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
