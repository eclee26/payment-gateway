<x-app-layout :title="__('Payments')" :subtitle="__('Every payment created with your API keys.')">
    <div class="card overflow-hidden">
        @include('dashboard.payments._table')

        @if ($payments->hasPages())
            <div class="border-t border-slate-100 px-5 py-3">{{ $payments->links() }}</div>
        @endif
    </div>
</x-app-layout>
