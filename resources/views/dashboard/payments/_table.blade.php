@if ($payments->isEmpty())
    <x-empty-state icon="banknotes" :title="__('No payments yet.')" :description="__('Payments created through the API appear here.')" />
@else
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('ID') }}</th>
                    <th class="!text-end">{{ __('Amount') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Method') }}</th>
                    <th>{{ __('Created') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payments as $payment)
                    <tr>
                        <td class="font-mono text-xs">
                            <a href="{{ route('dashboard.payments.show', $payment) }}" class="link">{{ $payment->id }}</a>
                        </td>
                        <td class="text-end tabular-nums font-medium text-slate-900">{{ \App\Support\Money::format($payment->amount, $payment->currency) }}</td>
                        <td><x-payment-status :status="$payment->status" /></td>
                        <td>
                            @if ($payment->card_last4)
                                {{ ucfirst($payment->card_brand) }} •••• {{ $payment->card_last4 }}
                            @else
                                {{ $payment->payment_method->label() }}
                            @endif
                        </td>
                        <td class="whitespace-nowrap text-slate-500">{{ $payment->created_at->toDayDateTimeString() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
