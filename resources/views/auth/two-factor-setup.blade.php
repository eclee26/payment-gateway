<x-guest-layout :title="__('Set up two-factor authentication')" :subtitle="__('Two-factor authentication is required for every account. You only need to do this once.')">
    <ol class="space-y-6 text-sm">
        <li>
            <p class="font-medium text-slate-900">{{ __('1. Scan this QR code') }}</p>
            <p class="mt-1 text-slate-500">{{ __('Use Google Authenticator, Microsoft Authenticator, 1Password, Authy or any TOTP app.') }}</p>
            <div class="mt-4 inline-block rounded-xl border border-slate-200 bg-white p-3 shadow-sm [&>svg]:size-44">
                {!! $qrCode !!}
            </div>
        </li>

        <li x-data="copyable">
            <p class="font-medium text-slate-900">{{ __('Or enter this key manually') }}</p>
            <div class="mt-2 flex items-center gap-2">
                <code x-ref="code" class="flex-1 break-all rounded-lg bg-slate-100 px-3 py-2 font-mono text-xs text-slate-700">{{ trim(chunk_split($secret, 4, ' ')) }}</code>
                <button type="button" class="btn-secondary btn-sm" @click="copy()" x-text="copied ? '{{ __('Copied') }}' : '{{ __('Copy') }}'">{{ __('Copy') }}</button>
            </div>
        </li>

        <li>
            <p class="font-medium text-slate-900">{{ __('2. Enter the 6-digit code') }}</p>
            <form method="POST" action="{{ route('two-factor.setup.store') }}" class="mt-3 space-y-4">
                @csrf
                <div>
                    <x-input-label for="code" :value="__('Authentication code')" class="sr-only" />
                    <x-text-input id="code" class="text-center font-mono text-lg tracking-[0.5em]" type="text" name="code"
                                  inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code" required autofocus />
                    <x-input-error :messages="$errors->get('code')" class="mt-2" />
                </div>
                <x-primary-button class="w-full">{{ __('Enable two-factor authentication') }}</x-primary-button>
            </form>
        </li>
    </ol>

    <form method="POST" action="{{ route('logout') }}" class="mt-8 text-center">
        @csrf
        <button type="submit" class="text-sm text-slate-500 hover:text-slate-700">{{ __('Log out') }}</button>
    </form>
</x-guest-layout>
