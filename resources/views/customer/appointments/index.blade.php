<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Customer lounge') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('My appointments') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Request visits, check booking status, and keep your wellness history in one calm space.') }}
            </p>
        </div>

        <x-primary-button type="button">{{ __('Request appointment') }}</x-primary-button>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="space-y-6">
            <x-app-card class="overflow-hidden p-0">
                <div class="casa-dark-panel p-6 sm:p-8">
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-casa-gold">{{ __('Next visit') }}</p>
                    <h2 class="mt-3 font-display text-2xl font-black text-white">{{ __('Your appointment book is ready.') }}</h2>
                    <p class="mt-3 max-w-xl text-sm leading-7 text-casa-bg/80">
                        {{ __('Upcoming bookings and pending requests will appear here once the appointment workflow is connected.') }}
                    </p>
                </div>
                <div class="grid gap-4 p-5 sm:grid-cols-3">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Upcoming') }}</p>
                        <p class="mt-2 font-display text-2xl font-black text-casa-text">0</p>
                    </div>
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Pending') }}</p>
                        <p class="mt-2 font-display text-2xl font-black text-casa-text">0</p>
                    </div>
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Completed') }}</p>
                        <p class="mt-2 font-display text-2xl font-black text-casa-text">0</p>
                    </div>
                </div>
            </x-app-card>

            <x-empty-state
                title="{{ __('No appointment requests yet') }}"
                description="{{ __('Start with a service, preferred date, and notes. Staff will confirm the final booking time.') }}"
            >
                <x-slot name="action">
                    <x-primary-button type="button">{{ __('Request appointment') }}</x-primary-button>
                </x-slot>
            </x-empty-state>
        </section>

        <aside class="space-y-4">
            <x-app-card>
                <p class="casa-section-label">{{ __('Spa care') }}</p>
                <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Booking flow') }}</h2>
                <div class="mt-5 space-y-4">
                    <div class="flex gap-3">
                        <span class="mt-1 grid size-8 shrink-0 place-items-center rounded-full bg-casa-primary text-xs font-black text-white">1</span>
                        <p class="text-sm leading-6 text-casa-muted">{{ __('Send a preferred service and time.') }}</p>
                    </div>
                    <div class="flex gap-3">
                        <span class="mt-1 grid size-8 shrink-0 place-items-center rounded-full bg-casa-green text-xs font-black text-white">2</span>
                        <p class="text-sm leading-6 text-casa-muted">{{ __('Staff reviews availability.') }}</p>
                    </div>
                    <div class="flex gap-3">
                        <span class="mt-1 grid size-8 shrink-0 place-items-center rounded-full bg-casa-gold text-xs font-black text-casa-wood">3</span>
                        <p class="text-sm leading-6 text-casa-muted">{{ __('You receive the confirmed schedule.') }}</p>
                    </div>
                </div>
            </x-app-card>
        </aside>
    </div>
</x-app-layout>
