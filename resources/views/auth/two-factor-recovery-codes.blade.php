<x-guest-layout :title="__('Save your recovery codes')" :subtitle="__('If you lose access to your authenticator app, each of these codes lets you sign in once. They will not be shown again.')">
    <div x-data="copyable" class="space-y-4">
        <div x-ref="code" class="grid grid-cols-2 gap-2 rounded-xl border border-slate-200 bg-slate-50 p-4 font-mono text-sm text-slate-800">@foreach ($codes as $code)<span>{{ $code }}</span>
@endforeach</div>

        <div class="flex gap-2">
            <button type="button" class="btn-secondary flex-1" @click="copy()">
                <x-icon name="clipboard" class="size-4" />
                <span x-text="copied ? '{{ __('Copied') }}' : '{{ __('Copy codes') }}'">{{ __('Copy codes') }}</span>
            </button>
        </div>

        <div class="flex items-start gap-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
            <x-icon name="exclamation-triangle" class="text-amber-500" />
            <p>{{ __('Store them somewhere safe, like a password manager. Anyone with these codes and your password can sign in.') }}</p>
        </div>

        <a href="{{ route('dashboard') }}" class="btn-primary w-full">{{ __('I have saved my codes — continue') }}</a>
    </div>
</x-guest-layout>
