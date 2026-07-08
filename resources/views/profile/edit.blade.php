<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Account') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Profile') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Keep your contact details and password current for Casa Paraiso workflows.') }}
            </p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <x-app-card padding="p-4 sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </x-app-card>

        <x-app-card padding="p-4 sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </x-app-card>

        <x-app-card padding="p-4 sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.delete-user-form')
            </div>
        </x-app-card>
    </div>
</x-app-layout>
