<x-app-layout :title="\App\Support\Money::format($payment->amount, $payment->currency)" :subtitle="$payment->id">
    <x-slot name="breadcrumbs">
        <a href="{{ route('dashboard.payments.index') }}" class="hover:text-slate-700">{{ __('Payments') }}</a>
        <x-icon name="chevron-right" class="size-4 text-slate-300" />
        <span class="font-mono font-medium text-slate-700">{{ $payment->id }}</span>
    </x-slot>

    <x-slot name="actions">
        <x-payment-status :status="$payment->status" class="text-sm" />
    </x-slot>

    @if (config('gateway.simulator_enabled') && $payment->status === \App\Enums\PaymentStatus::RequiresAction && $payment->provider?->driver === 'simulated')
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <span>{{ __('Simulator: this payment is waiting for the customer. Act as the provider to complete it.') }}</span>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('dashboard.payments.simulate', $payment) }}">
                    @csrf
                    <input type="hidden" name="outcome" value="succeeded">
                    <x-primary-button>{{ __('Simulate success') }}</x-primary-button>
                </form>
                <form method="POST" action="{{ route('dashboard.payments.simulate', $payment) }}">
                    @csrf
                    <input type="hidden" name="outcome" value="failed">
                    <x-secondary-button type="submit">{{ __('Simulate failure') }}</x-secondary-button>
                </form>
            </div>
        </div>
    @endif

    <div class="card p-6">
        <dl class="grid gap-5 sm:grid-cols-3 text-sm">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Amount') }}</dt>
                <dd class="text-slate-900 tabular-nums">{{ \App\Support\Money::format($payment->amount, $payment->currency) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Captured') }}</dt>
                <dd class="text-slate-900 tabular-nums">{{ \App\Support\Money::format($payment->amount_captured, $payment->currency) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Refunded') }}</dt>
                <dd class="text-slate-900 tabular-nums">{{ \App\Support\Money::format($payment->amount_refunded, $payment->currency) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Status') }}</dt>
                <dd><x-payment-status :status="$payment->status" /></dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Payment method') }}</dt>
                <dd class="text-slate-900">{{ $payment->payment_method->label() }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Provider') }}</dt>
                <dd class="text-slate-900">{{ $payment->provider?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Fee') }}</dt>
                <dd class="text-slate-900 tabular-nums">{{ \App\Support\Money::format($payment->fee_amount, $payment->currency) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Capture method') }}</dt>
                <dd class="text-slate-900">{{ $payment->capture_method->value }}</dd>
            </div>
            @if ($payment->card_last4)
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Card') }}</dt>
                    <dd class="text-slate-900">{{ ucfirst($payment->card_brand) }} •••• {{ $payment->card_last4 }} ({{ sprintf('%02d', $payment->card_exp_month) }}/{{ $payment->card_exp_year }})</dd>
                </div>
            @endif
            @if ($payment->next_action)
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Next action') }}</dt>
                    <dd class="text-slate-900 font-mono break-all">{{ json_encode($payment->next_action, JSON_UNESCAPED_SLASHES) }}</dd>
                </div>
            @endif
            @if ($payment->failure_code)
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Failure code') }}</dt>
                    <dd class="text-rose-600">{{ $payment->failure_code }}</dd>
                </div>
            @endif
            @if ($payment->description)
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Description') }}</dt>
                    <dd class="text-slate-900">{{ $payment->description }}</dd>
                </div>
            @endif
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Created') }}</dt>
                <dd class="text-slate-900">{{ $payment->created_at->toDayDateTimeString() }}</dd>
            </div>
        </dl>
    </div>

    <div class="card overflow-hidden">
        <div class="card-header"><h2 class="card-title">{{ __('Refunds') }}</h2></div>
        @if ($payment->refunds->isEmpty())
            <p class="p-5 text-sm text-slate-500">{{ __('No refunds.') }}</p>
        @else
            <table class="data-table">
                <tbody>
                    @foreach ($payment->refunds as $refund)
                        <tr>
                            <td class="font-mono text-xs">{{ $refund->id }}</td>
                            <td class="text-end tabular-nums">{{ \App\Support\Money::format($refund->amount, $payment->currency) }}</td>
                            <td>{{ $refund->reason ? str_replace('_', ' ', $refund->reason) : '—' }}</td>
                            <td class="text-slate-500">{{ $refund->created_at->toDayDateTimeString() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-app-layout>
