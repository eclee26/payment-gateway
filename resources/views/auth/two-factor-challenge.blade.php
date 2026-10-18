<x-guest-layout :title="__('Two-factor authentication')">
    <div x-data="{ recovery: {{ $errors->has('recovery_code') ? 'true' : 'false' }} }">
        <p class="text-sm text-slate-500" x-show="! recovery">
            {{ __('Enter the 6-digit code from your authenticator app.') }}
        </p>
        <p class="text-sm text-slate-500" x-show="recovery" x-cloak>
            {{ __('Enter one of your emergency recovery codes. Each code can be used once.') }}
        </p>

        <form method="POST" action="{{ route('two-factor.challenge.store') }}" class="mt-6 space-y-5">
            @csrf

            <div x-show="! recovery">
                <x-input-label for="code" :value="__('Authentication code')" />
                <x-text-input id="code" class="mt-1 text-center font-mono text-lg tracking-[0.5em]" type="text" name="code"
                              inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code" autofocus
                              x-bind:disabled="recovery" />
                <x-input-error :messages="$errors->get('code')" class="mt-2" />
            </div>

            <div x-show="recovery" x-cloak>
                <x-input-label for="recovery_code" :value="__('Recovery code')" />
                <x-text-input id="recovery_code" class="mt-1 font-mono" type="text" name="recovery_code" autocomplete="off"
                              x-bind:disabled="! recovery" />
                <x-input-error :messages="$errors->get('recovery_code')" class="mt-2" />
            </div>

            <x-primary-button class="w-full">{{ __('Verify') }}</x-primary-button>
        </form>

        <div class="mt-6 flex items-center justify-between text-sm">
            <button type="button" class="link" x-show="! recovery" @click="recovery = true; $nextTick(() => document.getElementById('recovery_code').focus())">
                {{ __('Use a recovery code') }}
            </button>
            <button type="button" class="link" x-show="recovery" x-cloak @click="recovery = false; $nextTick(() => document.getElementById('code').focus())">
                {{ __('Use an authentication code') }}
            </button>
            <a href="{{ route('login') }}" class="text-slate-500 hover:text-slate-700">{{ __('Cancel') }}</a>
        </div>
    </div>
</x-guest-layout>
