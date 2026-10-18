<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Providers') }}
            </h2>
            <a href="{{ route('admin.providers.create') }}" class="text-sm text-indigo-600 hover:text-indigo-900">{{ __('Add provider') }}</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg">
                @if ($providers->isEmpty())
                    <p class="p-6 text-sm text-gray-500">{{ __('No providers yet.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('Provider') }}</th>
                                    <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('Driver') }}</th>
                                    <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('Payment methods (pricing)') }}</th>
                                    <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('Status') }}</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($providers as $provider)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <div class="text-gray-900">{{ $provider->name }}</div>
                                            <div class="font-mono text-xs text-gray-500">{{ $provider->code }}</div>
                                        </td>
                                        <td class="px-4 py-3 font-mono">{{ $provider->driver }}</td>
                                        <td class="px-4 py-3">
                                            @forelse ($provider->paymentMethods->where('is_active', true) as $row)
                                                <span class="inline-block me-2">{{ $row->payment_method->label() }} <span class="text-gray-500">({{ $row->pricingLabel() }})</span></span>
                                            @empty
                                                <span class="text-gray-500">—</span>
                                            @endforelse
                                        </td>
                                        <td class="px-4 py-3">{{ $provider->is_active ? __('Active') : __('Inactive') }}</td>
                                        <td class="px-4 py-3 text-end">
                                            <a href="{{ route('admin.providers.edit', $provider) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('Edit') }}</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
