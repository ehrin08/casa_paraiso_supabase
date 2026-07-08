<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Staff module') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Appointments') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Handle assigned appointments and pending requests for services you can perform.') }}
            </p>
        </div>
    </x-slot>

    <x-app-card>
        <div class="flex flex-col gap-4 border-b border-casa-border pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="casa-section-label">{{ __('Daily queue') }}</p>
                <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Operational appointments') }}</h2>
            </div>
            <form method="GET" action="{{ route('staff.appointments.index') }}" class="flex flex-col gap-3 sm:flex-row">
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
                <x-empty-state title="{{ __('No appointments in your queue') }}" description="{{ __('Assigned visits and eligible pending requests will appear here.') }}" />
            @else
                <x-table-shell>
                    <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                        <tr>
                            <th class="px-4 py-3">{{ __('No.') }}</th>
                            <th class="px-4 py-3">{{ __('Customer') }}</th>
                            <th class="px-4 py-3">{{ __('Service') }}</th>
                            <th class="px-4 py-3">{{ __('Schedule') }}</th>
                            <th class="px-4 py-3">{{ __('Status') }}</th>
                            <th class="px-4 py-3">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-casa-border text-sm">
                        @foreach ($appointments as $appointment)
                            <tr>
                                <td class="px-4 py-4 font-semibold text-casa-text">{{ $appointment->appointment_number }}</td>
                                <td class="px-4 py-4 text-casa-muted">{{ $appointment->customerProfile?->user?->name }}</td>
                                <td class="px-4 py-4 text-casa-muted">{{ $appointment->service?->name }}</td>
                                <td class="px-4 py-4 text-casa-muted">{{ ($appointment->scheduled_start_at ?? $appointment->requested_start_at)?->format('M d, Y g:i A') }}</td>
                                <td class="px-4 py-4"><x-status-badge>{{ ucfirst(str_replace('_', ' ', $appointment->status)) }}</x-status-badge></td>
                                <td class="px-4 py-4">
                                    <a href="{{ route('staff.appointments.show', $appointment) }}" class="font-bold text-casa-primary hover:text-casa-primary-dark">{{ __('Open') }}</a>
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
</x-app-layout>
