<x-app-layout :title="__('API key created')">
    @push('head')
        {{-- Never keep this page in Turbo's back/forward cache: it contains a secret. --}}
        <meta name="turbo-cache-control" content="no-cache">
    @endpush

    <div class="card max-w-3xl">
        <div class="card-body space-y-5">
            <div class="flex items-start gap-3 rounded-lg bg-amber-50 p-4 text-sm text-amber-800">
                <x-icon name="exclamation-triangle" class="text-amber-500" />
                <p>{{ __('Copy the secret now. It will not be shown again.') }}</p>
            </div>

            <div x-data="copyable">
                <x-input-label :value="__('Key ID (X-Api-Key header)')" />
                <div class="mt-1 flex items-center gap-2">
                    <code x-ref="code" class="block flex-1 break-all rounded-lg bg-slate-100 p-3 font-mono text-sm">{{ $key->key_id }}</code>
                    <button type="button" class="btn-secondary btn-sm" @click="copy()" x-text="copied ? '{{ __('Copied') }}' : '{{ __('Copy') }}'">{{ __('Copy') }}</button>
                </div>
            </div>

            <div x-data="copyable">
                <x-input-label :value="__('Secret (used to compute X-Signature)')" />
                <div class="mt-1 flex items-center gap-2">
                    <code x-ref="code" class="block flex-1 break-all rounded-lg bg-slate-100 p-3 font-mono text-sm">{{ $key->secret }}</code>
                    <button type="button" class="btn-secondary btn-sm" @click="copy()" x-text="copied ? '{{ __('Copied') }}' : '{{ __('Copy') }}'">{{ __('Copy') }}</button>
                </div>
            </div>

            <a href="{{ route('dashboard.api-keys.index') }}" class="btn-secondary">{{ __('Back to API keys') }}</a>
        </div>
    </div>
</x-app-layout>
