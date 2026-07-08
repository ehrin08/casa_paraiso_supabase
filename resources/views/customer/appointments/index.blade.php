<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Customer lounge') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('My appointments') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Request visits, check booking status, and keep your wellness history in one calm space.') }}
            </p>
        </div>

        <a href="{{ route('customer.appointments.create') }}" class="casa-button-primary">{{ __('Request appointment') }}</a>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="space-y-6">
            <x-app-card class="overflow-hidden p-0">
                <div class="casa-dark-panel p-6 sm:p-8">
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-casa-gold">{{ __('Next visit') }}</p>
                    <h2 class="mt-3 font-display text-2xl font-black text-white">{{ __('Your appointment book is ready.') }}</h2>
                        <p class="mt-3 max-w-xl text-sm leading-7 text-casa-bg/80">
                        {{ __('Upcoming bookings, pending requests, and completed visits stay organized here.') }}
                    </p>
                </div>
                <div class="grid gap-4 p-5 sm:grid-cols-3">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Upcoming') }}</p>
                        <p class="mt-2 font-display text-2xl font-black text-casa-text">{{ $summary['upcoming'] ?? 0 }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Pending') }}</p>
                        <p class="mt-2 font-display text-2xl font-black text-casa-text">{{ $summary['pending'] ?? 0 }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Completed') }}</p>
                        <p class="mt-2 font-display text-2xl font-black text-casa-text">{{ $summary['completed'] ?? 0 }}</p>
                    </div>
                </div>
            </x-app-card>

            @if ($appointments->isEmpty())
                <x-empty-state
                    title="{{ __('No appointment requests yet') }}"
                    description="{{ __('Start with a service, preferred date, and notes. Staff will confirm the final booking time.') }}"
                >
                    <x-slot name="action">
                        <a href="{{ route('customer.appointments.create') }}" class="casa-button-primary">{{ __('Request appointment') }}</a>
                    </x-slot>
                </x-empty-state>
            @else
                <x-app-card>
                    <div class="flex flex-col gap-3 border-b border-casa-border pb-5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="casa-section-label">{{ __('Appointment history') }}</p>
                            <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Recent requests') }}</h2>
                        </div>
                        <x-status-badge>{{ trans_choice(':count record|:count records', $appointments->count()) }}</x-status-badge>
                    </div>

                    <div class="mt-5">
                        <x-table-shell>
                            <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                                <tr>
                                    <th class="px-4 py-3">{{ __('No.') }}</th>
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
                                        $statusTone = match ($appointment->status) {
                                            \App\Models\Appointment::STATUS_CONFIRMED,
                                            \App\Models\Appointment::STATUS_COMPLETED => 'success',
                                            \App\Models\Appointment::STATUS_CANCELLED,
                                            \App\Models\Appointment::STATUS_NO_SHOW => 'danger',
                                            default => 'warning',
                                        };
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-4 font-semibold text-casa-text">{{ $appointment->appointment_number }}</td>
                                        <td class="px-4 py-4 text-casa-muted">{{ $appointment->service?->name ?? __('Service') }}</td>
                                        <td class="px-4 py-4 text-casa-muted">{{ ($appointment->scheduled_start_at ?? $appointment->requested_start_at)?->format('M d, Y g:i A') }}</td>
                                        <td class="px-4 py-4 text-casa-muted">{{ $appointment->staffProfile?->user?->name ?? __('Pending') }}</td>
                                        <td class="px-4 py-4"><x-status-badge :tone="$statusTone">{{ __(ucfirst(str_replace('_', ' ', $appointment->status))) }}</x-status-badge></td>
                                        <td class="px-4 py-4">
                                            <a href="{{ route('customer.appointments.show', $appointment) }}" class="font-bold text-casa-primary hover:text-casa-primary-dark">{{ __('Open') }}</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-table-shell>

                        <div class="mt-5">
                            {{ $appointments->links() }}
                        </div>
                    </div>
                </x-app-card>
            @endif
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
