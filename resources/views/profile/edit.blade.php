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

    <div x-data="{ section: 'profile' }" class="grid gap-4 lg:grid-cols-[15rem_minmax(0,1fr)]">
        <aside class="casa-card p-3">
            <div class="grid gap-2">
                <button type="button" class="casa-nav-control" :class="section === 'profile' ? 'casa-nav-control-active' : ''" @click="section = 'profile'">
                    {{ __('Profile details') }}
                </button>
                <button type="button" class="casa-nav-control" :class="section === 'password' ? 'casa-nav-control-active' : ''" @click="section = 'password'">
                    {{ __('Password') }}
                </button>
                <button type="button" class="casa-nav-control" :class="section === 'delete' ? 'casa-nav-control-active' : ''" @click="section = 'delete'">
                    {{ __('Delete account') }}
                </button>
            </div>
        </aside>

        <x-app-card padding="p-4 sm:p-6">
            <div class="max-w-2xl" x-show="section === 'profile'">
                @include('profile.partials.update-profile-information-form')
            </div>

            <div class="max-w-2xl" x-show="section === 'password'" style="display: none;">
                @include('profile.partials.update-password-form')
            </div>

            <div class="max-w-2xl" x-show="section === 'delete'" style="display: none;">
                @include('profile.partials.delete-user-form')
            </div>
        </x-app-card>
    </div>
</x-app-layout>
