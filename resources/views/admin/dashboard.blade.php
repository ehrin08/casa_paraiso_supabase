<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Admin workspace') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Dashboard') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('A calm management overview for bookings, revenue, feedback, and promotion reviews.') }}
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <x-secondary-button type="button">{{ __('View reports') }}</x-secondary-button>
            <x-primary-button type="button">{{ __('Review requests') }}</x-primary-button>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <x-metric-card label="Today" value="0" meta="Appointments scheduled" tone="brown" />
            <x-metric-card label="Pending" value="0" meta="Requests awaiting review" tone="gold" />
            <x-metric-card label="Revenue" value="PHP 0" meta="Recorded today" tone="green" />
            <x-metric-card label="Feedback" value="0" meta="New reviews to read" tone="charcoal" />
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(320px,0.55fr)]">
            <x-app-card>
                <div class="flex flex-col gap-3 border-b border-casa-border pb-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="casa-section-label">{{ __('Appointment queue') }}</p>
                        <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Pending requests') }}</h2>
                    </div>
                    <x-status-badge tone="warning">{{ __('Ready for Phase 6') }}</x-status-badge>
                </div>

                <div class="mt-5">
                    <x-table-shell>
                        <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                            <tr>
                                <th class="px-4 py-3">{{ __('Customer') }}</th>
                                <th class="px-4 py-3">{{ __('Service') }}</th>
                                <th class="px-4 py-3">{{ __('Status') }}</th>
                                <th class="px-4 py-3">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-casa-border text-sm">
                            <tr>
                                <td class="px-4 py-4 font-semibold text-casa-text">{{ __('No requests yet') }}</td>
                                <td class="px-4 py-4 text-casa-muted">{{ __('Appointments module pending') }}</td>
                                <td class="px-4 py-4"><x-status-badge>{{ __('Empty') }}</x-status-badge></td>
                                <td class="px-4 py-4 text-casa-muted">{{ __('Add workflow later') }}</td>
                            </tr>
                        </tbody>
                    </x-table-shell>
                </div>
            </x-app-card>

            <aside class="casa-dark-panel rounded-[24px] p-6 shadow-casa-card">
                <p class="text-xs font-black uppercase tracking-[0.18em] text-casa-gold">{{ __('Management focus') }}</p>
                <h2 class="mt-4 font-display text-2xl font-black text-white">{{ __('Spa operations, without visual noise.') }}</h2>
                <p class="mt-4 text-sm leading-7 text-casa-bg/80">
                    {{ __('The admin interface uses compact cards and tables over warm spa materials, keeping daily work quick to scan.') }}
                </p>
                <div class="casa-divider my-6"></div>
                <div class="space-y-3 text-sm font-semibold text-casa-bg/80">
                    <p>{{ __('Appointments and staff schedules') }}</p>
                    <p>{{ __('Transactions and exports') }}</p>
                    <p>{{ __('Feedback sentiment and RFM suggestions') }}</p>
                </div>
            </aside>
        </section>
    </div>
</x-app-layout>
