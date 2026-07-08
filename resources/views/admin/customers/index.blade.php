<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Admin module') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Customers') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Review customer records, visit history, payments, feedback, and promotion context.') }}
            </p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="grid gap-4 md:grid-cols-3">
            <x-metric-card label="Customers" :value="$totalCustomers" meta="Profile records" tone="brown" />
            <x-metric-card label="Showing" :value="$customers->count()" meta="Current page" tone="green" />
            <x-metric-card label="Search" value="{{ $search !== '' ? __('Active') : __('Ready') }}" meta="Name, email, phone, code" tone="gold" />
        </section>

        <x-app-card>
            <div class="flex flex-col gap-4 border-b border-casa-border pb-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="casa-section-label">{{ __('Customer records') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Profile list') }}</h2>
                </div>
                <form method="GET" action="{{ route('admin.customers.index') }}" class="flex w-full flex-col gap-3 sm:flex-row lg:max-w-xl">
                    <input type="search" name="q" value="{{ $search }}" class="casa-input" placeholder="{{ __('Search customers') }}">
                    <button type="submit" class="casa-button-secondary">{{ __('Filter') }}</button>
                </form>
            </div>

            <div class="mt-5">
                @if ($customers->isEmpty())
                    <x-empty-state
                        title="{{ __('No customers found') }}"
                        description="{{ __('Customer profiles appear after registration or when seeded for demo workflows.') }}"
                    />
                @else
                    <x-table-shell>
                        <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                            <tr>
                                <th class="px-4 py-3">{{ __('Customer') }}</th>
                                <th class="px-4 py-3">{{ __('Contact') }}</th>
                                <th class="px-4 py-3">{{ __('History') }}</th>
                                <th class="px-4 py-3">{{ __('Preference') }}</th>
                                <th class="px-4 py-3">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-casa-border text-sm">
                            @foreach ($customers as $customer)
                                <tr>
                                    <td class="px-4 py-4">
                                        <a href="{{ route('admin.customers.show', $customer) }}" class="font-bold text-casa-text hover:text-casa-primary">
                                            {{ $customer->user->name }}
                                        </a>
                                        <p class="mt-1 text-xs text-casa-muted">{{ $customer->customer_code }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-casa-muted">
                                        <p>{{ $customer->user->email }}</p>
                                        <p>{{ $customer->user->phone ?: __('No phone') }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-casa-muted">
                                        {{ trans_choice(':count appointment|:count appointments', $customer->appointments_count) }},
                                        {{ trans_choice(':count transaction|:count transactions', $customer->transactions_count) }},
                                        {{ trans_choice(':count feedback|:count feedback', $customer->feedback_count) }}
                                    </td>
                                    <td class="px-4 py-4">
                                        <x-status-badge>{{ $customer->contact_preference ?: __('Not set') }}</x-status-badge>
                                    </td>
                                    <td class="px-4 py-4">
                                        <a href="{{ route('admin.customers.show', $customer) }}" class="font-bold text-casa-primary hover:text-casa-primary-dark">
                                            {{ __('Open') }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>

                    <div class="mt-5">
                        {{ $customers->links() }}
                    </div>
                @endif
            </div>
        </x-app-card>
    </div>
</x-app-layout>
