<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Payment methods') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <p class="p-6 pb-0 text-sm text-gray-500">{{ __('Send one of these as payment_method when creating a payment. Contact support to enable a method or change its provider.') }}</p>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('Method') }}</th>
                                <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('Provider') }}</th>
                                <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('Fee') }}</th>
                                <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('Currencies') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($methods as $value => $entry)
                                <tr>
                                    <td class="px-4 py-3">{{ $entry['method']->label() }} <span class="font-mono text-xs text-gray-500">{{ $value }}</span></td>
                                    @if ($entry['pricing'])
                                        <td class="px-4 py-3">{{ $entry['pricing']->provider->name }}</td>
                                        <td class="px-4 py-3">{{ $entry['pricing']->pricingLabel() }}</td>
                                    @else
                                        <td class="px-4 py-3 text-gray-500" colspan="2">{{ __('Not enabled') }}</td>
                                    @endif
                                    <td class="px-4 py-3 text-gray-500">{{ implode(', ', $entry['method']->currencies()) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
