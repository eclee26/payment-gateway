<x-app-layout>
    @push('head')
        {{-- Never keep this page in Turbo's back/forward cache: it contains a secret. --}}
        <meta name="turbo-cache-control" content="no-cache">
    @endpush

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('New webhook secret') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                <div class="rounded-md bg-yellow-50 p-4 text-sm text-yellow-800">
                    {{ __('Copy the secret now and update your endpoint. It will not be shown again.') }}
                </div>

                <code class="block break-all rounded bg-gray-100 p-3 font-mono text-sm">{{ $secret }}</code>

                <a href="{{ route('dashboard.webhooks.edit') }}" class="inline-block text-sm text-indigo-600 hover:underline">{{ __('Back to webhooks') }}</a>
            </div>
        </div>
    </div>
</x-app-layout>
