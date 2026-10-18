@props(['status'])

@php
    $class = match ($status) {
        \App\Enums\PaymentStatus::Succeeded => 'badge-green',
        \App\Enums\PaymentStatus::Failed => 'badge-red',
        \App\Enums\PaymentStatus::RequiresAction, \App\Enums\PaymentStatus::RequiresCapture => 'badge-amber',
        \App\Enums\PaymentStatus::PartiallyRefunded, \App\Enums\PaymentStatus::Refunded => 'badge-blue',
        default => 'badge-gray',
    };
@endphp

<span {{ $attributes->merge(['class' => $class]) }}>{{ str_replace('_', ' ', $status->value) }}</span>
