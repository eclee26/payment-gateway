<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight font-mono">
            {{ $payment->id }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <dl class="grid gap-4 sm:grid-cols-3 text-sm">
                    <div>
                        <dt class="text-gray-500">{{ __('Amount') }}</dt>
                        <dd class="text-gray-900 tabular-nums">{{ \App\Support\Money::format($payment->amount, $payment->currency) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('Captured') }}</dt>
                        <dd class="text-gray-900 tabular-nums">{{ \App\Support\Money::format($payment->amount_captured, $payment->currency) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('Refunded') }}</dt>
                        <dd class="text-gray-900 tabular-nums">{{ \App\Support\Money::format($payment->amount_refunded, $payment->currency) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('Status') }}</dt>
                        <dd class="text-gray-900">{{ str_replace('_', ' ', $payment->status->value) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('Capture method') }}</dt>
                        <dd class="text-gray-900">{{ $payment->capture_method->value }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('Card') }}</dt>
                        <dd class="text-gray-900">{{ ucfirst($payment->card_brand) }} •••• {{ $payment->card_last4 }} ({{ sprintf('%02d', $payment->card_exp_month) }}/{{ $payment->card_exp_year }})</dd>
                    </div>
                    @if ($payment->failure_code)
                        <div>
                            <dt class="text-gray-500">{{ __('Failure code') }}</dt>
                            <dd class="text-red-600">{{ $payment->failure_code }}</dd>
                        </div>
                    @endif
                    @if ($payment->description)
                        <div class="sm:col-span-2">
                            <dt class="text-gray-500">{{ __('Description') }}</dt>
                            <dd class="text-gray-900">{{ $payment->description }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-gray-500">{{ __('Created') }}</dt>
                        <dd class="text-gray-900">{{ $payment->created_at->toDayDateTimeString() }}</dd>
                    </div>
                </dl>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg">
                <h3 class="p-6 pb-0 font-medium text-gray-900">{{ __('Refunds') }}</h3>
                @if ($payment->refunds->isEmpty())
                    <p class="p-6 text-sm text-gray-500">{{ __('No refunds.') }}</p>
                @else
                    <table class="mt-4 min-w-full divide-y divide-gray-200 text-sm">
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($payment->refunds as $refund)
                                <tr>
                                    <td class="px-4 py-3 font-mono">{{ $refund->id }}</td>
                                    <td class="px-4 py-3 text-end tabular-nums">{{ \App\Support\Money::format($refund->amount, $payment->currency) }}</td>
                                    <td class="px-4 py-3">{{ $refund->reason ? str_replace('_', ' ', $refund->reason) : '—' }}</td>
                                    <td class="px-4 py-3 text-gray-500">{{ $refund->created_at->toDayDateTimeString() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
