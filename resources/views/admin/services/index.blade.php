<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Admin module') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Services') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Maintain Casa Paraiso treatments, pricing, duration, and booking availability.') }}
            </p>
        </div>

        <a href="{{ route('admin.services.create') }}" class="casa-button-primary">{{ __('Add service') }}</a>
    </x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[18px] border border-casa-green/30 bg-casa-green/10 px-5 py-4 text-sm font-semibold text-casa-green">
                {{ __('Service catalog updated.') }}
            </div>
        @endif

        <section class="grid gap-4 md:grid-cols-3">
            <x-metric-card label="Active" :value="$activeCount" meta="Bookable treatments" tone="green" />
            <x-metric-card label="Inactive" :value="$inactiveCount" meta="Hidden from booking" tone="gold" />
            <x-metric-card label="Catalog" :value="$services->total()" meta="Total services" tone="brown" />
        </section>

        <x-app-card>
            <div class="flex flex-col gap-3 border-b border-casa-border pb-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="casa-section-label">{{ __('Catalog') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Treatment list') }}</h2>
                </div>
                <x-status-badge>{{ trans_choice(':count service|:count services', $services->total()) }}</x-status-badge>
            </div>

            <div class="mt-5">
                @if ($services->isEmpty())
                    <x-empty-state
                        title="{{ __('No services yet') }}"
                        description="{{ __('Add the first treatment with duration and pricing before staff assignments and appointments are connected.') }}"
                    >
                        <x-slot name="action">
                            <a href="{{ route('admin.services.create') }}" class="casa-button-primary">{{ __('Add service') }}</a>
                        </x-slot>
                    </x-empty-state>
                @else
                    <x-table-shell>
                        <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                            <tr>
                                <th class="px-4 py-3">{{ __('Service') }}</th>
                                <th class="px-4 py-3">{{ __('Duration') }}</th>
                                <th class="px-4 py-3">{{ __('Price') }}</th>
                                <th class="px-4 py-3">{{ __('Usage') }}</th>
                                <th class="px-4 py-3">{{ __('Status') }}</th>
                                <th class="px-4 py-3">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-casa-border text-sm">
                            @foreach ($services as $service)
                                <tr>
                                    <td class="px-4 py-4">
                                        <a href="{{ route('admin.services.show', $service) }}" class="font-bold text-casa-text hover:text-casa-primary">
                                            {{ $service->name }}
                                        </a>
                                        <p class="mt-1 text-xs text-casa-muted">{{ $service->slug }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-casa-muted">{{ $service->duration_minutes }} {{ __('min') }}</td>
                                    <td class="px-4 py-4 font-semibold text-casa-text">PHP {{ number_format((float) $service->price, 2) }}</td>
                                    <td class="px-4 py-4 text-casa-muted">
                                        {{ trans_choice(':count staff|:count staff', $service->staff_profiles_count) }},
                                        {{ trans_choice(':count appointment|:count appointments', $service->appointments_count) }}
                                    </td>
                                    <td class="px-4 py-4">
                                        <x-status-badge :tone="$service->is_active ? 'success' : 'dark'">
                                            {{ $service->is_active ? __('Active') : __('Inactive') }}
                                        </x-status-badge>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            <a href="{{ route('admin.services.edit', $service) }}" class="font-bold text-casa-primary hover:text-casa-primary-dark">
                                                {{ __('Edit') }}
                                            </a>
                                            <form method="POST" action="{{ route('admin.services.toggle', $service) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="font-bold text-casa-muted hover:text-casa-primary">
                                                    {{ $service->is_active ? __('Deactivate') : __('Activate') }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>

                    <div class="mt-5">
                        {{ $services->links() }}
                    </div>
                @endif
            </div>
        </x-app-card>
    </div>
</x-app-layout>
