<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Admin module') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Appointments') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Review requests, assign staff, confirm schedules, and track visit completion.') }}
            </p>
        </div>

        <a href="{{ route('admin.appointments.create') }}" class="casa-button-primary">{{ __('Add appointment') }}</a>
    </x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[18px] border border-casa-green/30 bg-casa-green/10 px-5 py-4 text-sm font-semibold text-casa-green">
                {{ __('Appointment records updated.') }}
            </div>
        @endif

        <section class="grid gap-4 md:grid-cols-3">
            <x-metric-card label="Pending" :value="$summary['pending']" meta="Requests awaiting review" tone="gold" />
            <x-metric-card label="Confirmed" :value="$summary['confirmed']" meta="Scheduled visits" tone="green" />
            <x-metric-card label="Completed" :value="$summary['completed']" meta="Finished services" tone="brown" />
        </section>

        <x-app-card>
            <div class="flex flex-col gap-4 border-b border-casa-border pb-5 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <p class="casa-section-label">{{ __('Queue') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Appointment list') }}</h2>
                </div>
                <form method="GET" action="{{ route('admin.appointments.index') }}" class="grid gap-3 sm:grid-cols-[1fr_auto_auto] xl:min-w-[42rem]">
                    <input type="search" name="q" value="{{ $search }}" class="casa-input" placeholder="{{ __('Search number, customer, service') }}">
                    <select name="status" class="casa-input">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach (\App\Models\Appointment::STATUSES as $option)
                            <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst(str_replace('_', ' ', $option)) }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="casa-button-secondary">{{ __('Filter') }}</button>
                </form>
            </div>

            <div class="mt-5">
                @if ($appointments->isEmpty())
                    <x-empty-state title="{{ __('No appointments found') }}" description="{{ __('Requests and scheduled visits will appear here once customers book or staff create records.') }}" />
                @else
                    <x-table-shell>
                        <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                            <tr>
                                <th class="px-4 py-3">{{ __('No.') }}</th>
                                <th class="px-4 py-3">{{ __('Customer') }}</th>
                                <th class="px-4 py-3">{{ __('Service') }}</th>
                                <th class="px-4 py-3">{{ __('Schedule') }}</th>
                                <th class="px-4 py-3">{{ __('Staff') }}</th>
                                <th class="px-4 py-3">{{ __('Status') }}</th>
                                <th class="px-4 py-3">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-casa-border text-sm">
                            @foreach ($appointments as $appointment)
                                @php
                                    $tone = match ($appointment->status) {
                                        \App\Models\Appointment::STATUS_CONFIRMED,
                                        \App\Models\Appointment::STATUS_COMPLETED => 'success',
                                        \App\Models\Appointment::STATUS_CANCELLED,
                                        \App\Models\Appointment::STATUS_NO_SHOW => 'danger',
                                        default => 'warning',
                                    };
                                @endphp
                                <tr>
                                    <td class="px-4 py-4 font-semibold text-casa-text">{{ $appointment->appointment_number }}</td>
                                    <td class="px-4 py-4 text-casa-muted">{{ $appointment->customerProfile?->user?->name }}</td>
                                    <td class="px-4 py-4 text-casa-muted">{{ $appointment->service?->name }}</td>
                                    <td class="px-4 py-4 text-casa-muted">{{ ($appointment->scheduled_start_at ?? $appointment->requested_start_at)?->format('M d, Y g:i A') }}</td>
                                    <td class="px-4 py-4 text-casa-muted">{{ $appointment->staffProfile?->user?->name ?? __('Pending') }}</td>
                                    <td class="px-4 py-4"><x-status-badge :tone="$tone">{{ ucfirst(str_replace('_', ' ', $appointment->status)) }}</x-status-badge></td>
                                    <td class="px-4 py-4">
                                        <a href="{{ route('admin.appointments.show', $appointment) }}" class="font-bold text-casa-primary hover:text-casa-primary-dark">{{ __('Open') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>

                    <div class="mt-5">
                        {{ $appointments->links() }}
                    </div>
                @endif
            </div>
        </x-app-card>
    </div>
</x-app-layout>
