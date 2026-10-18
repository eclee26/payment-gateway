<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Merchants') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg">
                @if ($merchants->isEmpty())
                    <p class="p-6 text-sm text-gray-500">{{ __('No merchants yet.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('Merchant') }}</th>
                                    <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('Payment methods') }}</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($merchants as $merchant)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <div class="text-gray-900">{{ $merchant->name }}</div>
                                            <div class="text-xs text-gray-500">{{ $merchant->user->email }}</div>
                                        </td>
                                        <td class="px-4 py-3">
                                            @forelse ($merchant->paymentMethodAssignments as $assignment)
                                                <span class="inline-block me-2">{{ $assignment->payment_method->label() }} → {{ $assignment->providerPaymentMethod->provider->name }}</span>
                                            @empty
                                                <span class="text-gray-500">{{ __('None assigned') }}</span>
                                            @endforelse
                                        </td>
                                        <td class="px-4 py-3 text-end">
                                            <a href="{{ route('admin.merchants.payment-methods.edit', $merchant) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('Assign') }}</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4">{{ $merchants->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
