<x-app-layout :title="__('Profile')" :subtitle="__('Manage your name, email address and password.')">
    <div class="card divide-y divide-slate-100">
        <div class="grid gap-6 p-6 md:grid-cols-3">
            @include('profile.partials.update-profile-information-form')
        </div>

        <div class="grid gap-6 p-6 md:grid-cols-3">
            @include('profile.partials.update-password-form')
        </div>

        <div class="grid gap-6 p-6 md:grid-cols-3">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</x-app-layout>
