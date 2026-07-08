<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Staff workspace') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Daily dashboard') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('A focused view for assigned appointments, pending requests, and service transactions.') }}
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('staff.customers.index') }}" class="casa-button-secondary">{{ __('Customer lookup') }}</a>
            <a href="{{ route('staff.appointments.index') }}" class="casa-button-primary">{{ __('Today schedule') }}</a>
        </div>
    </x-slot>

    <div class="grid gap-6 xl:grid-cols-[minmax(320px,0.8fr)_minmax(0,1.2fr)]">
        <section class="space-y-4">
            <x-metric-card label="Assigned today" :value="$summary['assignedToday'] ?? 0" meta="Confirmed appointments" tone="green" />
            <x-metric-card label="Pending" :value="$summary['pendingRequests'] ?? 0" meta="Requests needing action" tone="gold" />
            <x-metric-card label="Completed" :value="$summary['completedToday'] ?? 0" meta="Services finished today" tone="brown" />
        </section>

        <x-app-card>
            <div class="flex flex-col gap-3 border-b border-casa-border pb-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="casa-section-label">{{ __('Service flow') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Today appointments') }}</h2>
                </div>
                <x-status-badge tone="success">{{ trans_choice(':count assigned|:count assigned', $summary['assignedToday'] ?? 0) }}</x-status-badge>
            </div>

            <div class="mt-5 space-y-4">
                @if ($todayAppointments->isEmpty())
                    <x-empty-state
                        title="{{ __('No appointments assigned today') }}"
                        description="{{ __('Confirmed visits for today will appear here when appointments are scheduled.') }}"
                    />
                @else
                    <x-table-shell>
                        <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                            <tr>
                                <th class="px-4 py-3">{{ __('No.') }}</th>
                                <th class="px-4 py-3">{{ __('Time') }}</th>
                                <th class="px-4 py-3">{{ __('Customer') }}</th>
                                <th class="px-4 py-3">{{ __('Service') }}</th>
                                <th class="px-4 py-3">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-casa-border text-sm">
                            @foreach ($todayAppointments as $appointment)
                                <tr>
                                    <td class="px-4 py-4 font-semibold text-casa-text">{{ $appointment->appointment_number }}</td>
                                    <td class="px-4 py-4 font-semibold text-casa-text">{{ $appointment->scheduled_start_at?->format('g:i A') }}</td>
                                    <td class="px-4 py-4 text-casa-muted">{{ $appointment->customerProfile?->user?->name ?? __('Customer') }}</td>
                                    <td class="px-4 py-4 text-casa-muted">{{ $appointment->service?->name ?? __('Service') }}</td>
                                    <td class="px-4 py-4"><x-status-badge tone="success">{{ __(ucfirst($appointment->status)) }}</x-status-badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>
                @endif

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <p class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Confirm') }}</p>
                        <p class="mt-2 text-sm text-casa-text">{{ __('Review requested time and service.') }}</p>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <p class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Serve') }}</p>
                        <p class="mt-2 text-sm text-casa-text">{{ __('Track assigned guest care.') }}</p>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <p class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Record') }}</p>
                        <p class="mt-2 text-sm text-casa-text">{{ __('Log manual transactions.') }}</p>
                    </div>
                </div>
            </div>
        </x-app-card>
    </div>
</x-app-layout>
