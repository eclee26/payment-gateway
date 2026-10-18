@if ($payments->isEmpty())
    <p class="p-6 text-sm text-gray-500">{{ __('No payments yet.') }}</p>
@else
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('ID') }}</th>
                    <th class="px-4 py-3 text-end font-medium text-gray-500">{{ __('Amount') }}</th>
                    <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('Card') }}</th>
                    <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('Created') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($payments as $payment)
                    <tr>
                        <td class="px-4 py-3 font-mono">
                            <a href="{{ route('dashboard.payments.show', $payment) }}" class="text-indigo-600 hover:underline">{{ $payment->id }}</a>
                        </td>
                        <td class="px-4 py-3 text-end tabular-nums">{{ \App\Support\Money::format($payment->amount, $payment->currency) }}</td>
                        <td class="px-4 py-3">{{ str_replace('_', ' ', $payment->status->value) }}</td>
                        <td class="px-4 py-3">{{ ucfirst($payment->card_brand) }} •••• {{ $payment->card_last4 }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $payment->created_at->toDayDateTimeString() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
