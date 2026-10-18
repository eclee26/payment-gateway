@php
    // Breeze flashes keys rather than sentences for these.
    $status = match (session('status')) {
        'profile-updated' => __('Profile saved.'),
        'password-updated' => __('Password updated.'),
        'verification-link-sent' => __('A new verification link has been sent to your email address.'),
        default => session('status'),
    };
@endphp

@if ($status)
    <div x-data="{ show: true }" x-show="show" class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800" role="status">
        <x-icon name="check-circle" class="text-emerald-500" />
        <p class="flex-1">{{ $status }}</p>
        <button type="button" class="text-emerald-600 hover:text-emerald-800" @click="show = false">
            <span class="sr-only">{{ __('Dismiss') }}</span>
            <x-icon name="x-mark" class="size-4" />
        </button>
    </div>
@endif

@if (session('error'))
    <div class="flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800" role="alert">
        <x-icon name="exclamation-triangle" class="text-rose-500" />
        <p class="flex-1">{{ session('error') }}</p>
    </div>
@endif
