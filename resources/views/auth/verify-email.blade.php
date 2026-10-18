<x-guest-layout :title="__('Verify your email')" :subtitle="__('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.')">
    @if (session('status') == 'verification-link-sent')
        <x-auth-session-status class="mb-4" :status="__('A new verification link has been sent to the email address you provided during registration.')" />
    @endif

    <div class="flex items-center justify-between gap-4">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button>{{ __('Resend Verification Email') }}</x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn-ghost">{{ __('Log Out') }}</button>
        </form>
    </div>
</x-guest-layout>
