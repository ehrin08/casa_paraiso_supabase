<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Staff detail') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ $staffProfile->user->name }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ $staffProfile->specialization ?: __('Staff access, treatment eligibility, and future schedule connections.') }}
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.staff.index') }}" class="casa-button-secondary">{{ __('All staff') }}</a>
            <a href="{{ route('admin.staff.edit', $staffProfile) }}" class="casa-button-primary">{{ __('Edit staff') }}</a>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[18px] border border-casa-green/30 bg-casa-green/10 px-5 py-4 text-sm font-semibold text-casa-green">
                {{ __('Staff records updated.') }}
            </div>
        @endif

        <section class="grid gap-4 md:grid-cols-4">
            <x-metric-card label="Services" :value="$staffProfile->services_count" meta="Assigned treatments" tone="green" />
            <x-metric-card label="Schedules" :value="$staffProfile->weekly_schedules_count" meta="Phase 5C entries" tone="gold" />
            <x-metric-card label="Appointments" :value="$staffProfile->appointments_count" meta="Linked bookings" tone="brown" />
            <x-metric-card label="Access" value="{{ $staffProfile->user->is_active ? __('Active') : __('Inactive') }}" meta="Login status" tone="charcoal" />
        </section>

        <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <x-app-card>
                <div class="flex flex-col gap-3 border-b border-casa-border pb-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="casa-section-label">{{ __('Profile') }}</p>
                        <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Staff information') }}</h2>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-status-badge :tone="$staffProfile->user->is_active ? 'success' : 'dark'">
                            {{ $staffProfile->user->is_active ? __('Active account') : __('Inactive account') }}
                        </x-status-badge>
                        <x-status-badge :tone="$staffProfile->is_bookable ? 'success' : 'warning'">
                            {{ $staffProfile->is_bookable ? __('Bookable') : __('Not bookable') }}
                        </x-status-badge>
                    </div>
                </div>

                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Email') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $staffProfile->user->email }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Phone') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $staffProfile->user->phone ?: __('Not set') }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Position') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $staffProfile->position ?: __('Staff') }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Hire date') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $staffProfile->hire_date?->format('M d, Y') ?: __('Not set') }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4 sm:col-span-2">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Bio') }}</dt>
                        <dd class="mt-2 text-sm leading-6 text-casa-muted">{{ $staffProfile->bio ?: __('No bio has been added yet.') }}</dd>
                    </div>
                </dl>
            </x-app-card>

            <aside class="space-y-4">
                <x-app-card>
                    <p class="casa-section-label">{{ __('Assigned services') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Treatment eligibility') }}</h2>
                    <div class="mt-5 flex flex-wrap gap-2">
                        @forelse ($staffProfile->services as $service)
                            <x-status-badge :tone="$service->is_active ? 'success' : 'dark'">{{ $service->name }}</x-status-badge>
                        @empty
                            <p class="text-sm leading-6 text-casa-muted">{{ __('No services assigned yet.') }}</p>
                        @endforelse
                    </div>
                </x-app-card>

                <aside class="casa-dark-panel rounded-[24px] p-6 shadow-casa-card">
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-casa-gold">{{ __('Next workflow') }}</p>
                    <h2 class="mt-4 font-display text-2xl font-black text-white">{{ __('Ready for schedules.') }}</h2>
                    <p class="mt-4 text-sm leading-7 text-casa-bg/80">
                        {{ __('Phase 5C will add weekly availability and exceptions for bookable staff profiles.') }}
                    </p>
                </aside>
            </aside>
        </section>
    </div>
</x-app-layout>
