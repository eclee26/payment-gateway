<header>
    <h2 class="text-base font-semibold text-slate-900">{{ __('Profile Information') }}</h2>
    <p class="mt-1 text-sm text-slate-500">{{ __("Update your account's profile information and email address.") }}</p>
</header>

<div class="md:col-span-2">
    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="max-w-xl space-y-5">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <p class="mt-2 text-sm text-slate-700">
                    {{ __('Your email address is unverified.') }}
                    <button form="send-verification" class="link">{{ __('Click here to re-send the verification email.') }}</button>
                </p>
            @endif
        </div>

        <x-primary-button>{{ __('Save') }}</x-primary-button>
    </form>
</div>
