<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Admin module') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Staff') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Manage staff accounts, bookable profiles, and treatment eligibility before schedules and appointments are connected.') }}
            </p>
        </div>

        <a href="{{ route('admin.staff.create') }}" class="casa-button-primary">{{ __('Add staff') }}</a>
    </x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[18px] border border-casa-green/30 bg-casa-green/10 px-5 py-4 text-sm font-semibold text-casa-green">
                {{ __('Staff records updated.') }}
            </div>
        @endif

        <section class="grid gap-4 md:grid-cols-3">
            <x-metric-card label="Active accounts" :value="$activeAccountCount" meta="Can sign in" tone="green" />
            <x-metric-card label="Inactive accounts" :value="$inactiveAccountCount" meta="Access disabled" tone="gold" />
            <x-metric-card label="Bookable" :value="$bookableCount" meta="Available for appointments" tone="brown" />
        </section>

        <x-app-card>
            <div class="flex flex-col gap-3 border-b border-casa-border pb-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="casa-section-label">{{ __('Team') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Staff profiles') }}</h2>
                </div>
                <x-status-badge>{{ trans_choice(':count profile|:count profiles', $staffProfiles->total()) }}</x-status-badge>
            </div>

            <div class="mt-5">
                @if ($staffProfiles->isEmpty())
                    <x-empty-state
                        title="{{ __('No staff profiles yet') }}"
                        description="{{ __('Create staff accounts before assigning services, schedules, and appointment responsibilities.') }}"
                    >
                        <x-slot name="action">
                            <a href="{{ route('admin.staff.create') }}" class="casa-button-primary">{{ __('Add staff') }}</a>
                        </x-slot>
                    </x-empty-state>
                @else
                    <x-table-shell>
                        <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                            <tr>
                                <th class="px-4 py-3">{{ __('Staff') }}</th>
                                <th class="px-4 py-3">{{ __('Role') }}</th>
                                <th class="px-4 py-3">{{ __('Services') }}</th>
                                <th class="px-4 py-3">{{ __('Appointments') }}</th>
                                <th class="px-4 py-3">{{ __('Status') }}</th>
                                <th class="px-4 py-3">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-casa-border text-sm">
                            @foreach ($staffProfiles as $staffProfile)
                                <tr>
                                    <td class="px-4 py-4">
                                        <a href="{{ route('admin.staff.show', $staffProfile) }}" class="font-bold text-casa-text hover:text-casa-primary">
                                            {{ $staffProfile->user->name }}
                                        </a>
                                        <p class="mt-1 text-xs text-casa-muted">{{ $staffProfile->user->email }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-casa-muted">
                                        <p class="font-semibold text-casa-text">{{ $staffProfile->position ?: __('Staff') }}</p>
                                        <p class="mt-1 text-xs">{{ $staffProfile->specialization ?: __('No specialization yet') }}</p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            @forelse ($staffProfile->services as $service)
                                                <x-status-badge :tone="$service->is_active ? 'success' : 'dark'">{{ $service->name }}</x-status-badge>
                                            @empty
                                                <span class="text-casa-muted">{{ __('No services') }}</span>
                                            @endforelse
                                        </div>
                                        <p class="mt-2 text-xs text-casa-muted">{{ trans_choice(':count service|:count services', $staffProfile->services_count) }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-casa-muted">{{ trans_choice(':count appointment|:count appointments', $staffProfile->appointments_count) }}</td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            <x-status-badge :tone="$staffProfile->user->is_active ? 'success' : 'dark'">
                                                {{ $staffProfile->user->is_active ? __('Active') : __('Inactive') }}
                                            </x-status-badge>
                                            <x-status-badge :tone="$staffProfile->is_bookable ? 'success' : 'warning'">
                                                {{ $staffProfile->is_bookable ? __('Bookable') : __('Not bookable') }}
                                            </x-status-badge>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <a href="{{ route('admin.staff.edit', $staffProfile) }}" class="font-bold text-casa-primary hover:text-casa-primary-dark">
                                            {{ __('Edit') }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>

                    <div class="mt-5">
                        {{ $staffProfiles->links() }}
                    </div>
                @endif
            </div>
        </x-app-card>
    </div>
</x-app-layout>
