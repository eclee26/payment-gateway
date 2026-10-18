<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('API key created') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                <div class="rounded-md bg-yellow-50 p-4 text-sm text-yellow-800">
                    {{ __('Copy the secret now. It will not be shown again.') }}
                </div>

                <div>
                    <x-input-label :value="__('Key ID (X-Api-Key header)')" />
                    <code class="mt-1 block break-all rounded bg-gray-100 p-3 font-mono text-sm">{{ $key->key_id }}</code>
                </div>

                <div>
                    <x-input-label :value="__('Secret (used to compute X-Signature)')" />
                    <code class="mt-1 block break-all rounded bg-gray-100 p-3 font-mono text-sm">{{ $key->secret }}</code>
                </div>

                <a href="{{ route('dashboard.api-keys.index') }}" class="inline-block text-sm text-indigo-600 hover:underline">{{ __('Back to API keys') }}</a>
            </div>
        </div>
    </div>
</x-app-layout>
