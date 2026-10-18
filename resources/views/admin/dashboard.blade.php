<x-app-layout :title="__('Overview')" :subtitle="__('Welcome back, :name.', ['name' => Auth::user()->name])">
    @if ($stats === [])
        <div class="card">
            <x-empty-state icon="shield-check" :title="__('Nothing to show yet')" :description="__('Your role does not include any dashboard sections. Ask a super-admin if you need more access.')" />
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($stats as $stat)
                <x-stat :label="__($stat['label'])" :value="$stat['value']" :hint="__($stat['hint'])" :icon="$stat['icon']" :href="route($stat['route'])" />
            @endforeach
        </div>
    @endif

    @if ($paymentStats)
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat :label="__('Payments today')" :value="number_format($paymentStats['today'])" icon="banknotes" />
            <x-stat :label="__('Succeeded (30 days)')" :value="number_format($paymentStats['succeeded'])" icon="check-circle" />
            <x-stat :label="__('Failed (30 days)')" :value="number_format($paymentStats['failed'])" icon="exclamation-triangle" />
            <x-stat :label="__('Awaiting customer')" :value="number_format($paymentStats['pending'])" icon="arrow-path" />
        </div>
    @endif

    @if ($recentPayments)
        <div class="card overflow-hidden">
            <div class="card-header">
                <h2 class="card-title">{{ __('Latest payments across merchants') }}</h2>
            </div>
            @if ($recentPayments->isEmpty())
                <x-empty-state icon="banknotes" :title="__('No payments yet')" />
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>{{ __('ID') }}</th>
                                <th>{{ __('Merchant') }}</th>
                                <th class="!text-end">{{ __('Amount') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Method') }}</th>
                                <th>{{ __('Created') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentPayments as $payment)
                                <tr>
                                    <td class="font-mono text-xs">{{ $payment->id }}</td>
                                    <td><a href="{{ route('admin.merchants.show', $payment->merchant) }}" class="link">{{ $payment->merchant->name }}</a></td>
                                    <td class="text-end tabular-nums">{{ \App\Support\Money::format($payment->amount, $payment->currency) }}</td>
                                    <td><x-payment-status :status="$payment->status" /></td>
                                    <td>{{ $payment->payment_method->label() }}</td>
                                    <td class="whitespace-nowrap text-slate-500">{{ $payment->created_at->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</x-app-layout>
