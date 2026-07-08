<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Admin module') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Transactions') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Record manual payments and manage transaction status for service visits.') }}
            </p>
        </div>

        <a href="{{ route('admin.transactions.create') }}" class="casa-button-primary">{{ __('Record payment') }}</a>
    </x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[18px] border border-casa-green/30 bg-casa-green/10 px-5 py-4 text-sm font-semibold text-casa-green">
                {{ __('Transaction records updated.') }}
            </div>
        @endif

        <section class="grid gap-4 md:grid-cols-3">
            <x-metric-card label="Paid total" value="PHP {{ number_format((float) $summary['paid'], 2) }}" meta="Paid transactions" tone="green" />
            <x-metric-card label="Open balance" value="PHP {{ number_format((float) $summary['unpaid'], 2) }}" meta="Unpaid or partial" tone="gold" />
            <x-metric-card label="Records" :value="$summary['count']" meta="All transactions" tone="brown" />
        </section>

        <x-app-card>
            <div class="flex flex-col gap-4 border-b border-casa-border pb-5 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <p class="casa-section-label">{{ __('Payments') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Transaction list') }}</h2>
                </div>
                <form method="GET" action="{{ route('admin.transactions.index') }}" class="grid gap-3 sm:grid-cols-[1fr_auto_auto] xl:min-w-[42rem]">
                    <input type="search" name="q" value="{{ $search }}" class="casa-input" placeholder="{{ __('Search transaction, customer, service') }}">
                    <select name="payment_status" class="casa-input">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach (\App\Models\Transaction::PAYMENT_STATUSES as $option)
                            <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="casa-button-secondary">{{ __('Filter') }}</button>
                </form>
            </div>

            <div class="mt-5">
                @if ($transactions->isEmpty())
                    <x-empty-state title="{{ __('No transactions yet') }}" description="{{ __('Manual payment records will appear here after staff or admin records a service payment.') }}" />
                @else
                    <x-table-shell>
                        <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                            <tr>
                                <th class="px-4 py-3">{{ __('No.') }}</th>
                                <th class="px-4 py-3">{{ __('Customer') }}</th>
                                <th class="px-4 py-3">{{ __('Service') }}</th>
                                <th class="px-4 py-3">{{ __('Amount') }}</th>
                                <th class="px-4 py-3">{{ __('Status') }}</th>
                                <th class="px-4 py-3">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-casa-border text-sm">
                            @foreach ($transactions as $transaction)
                                <tr>
                                    <td class="px-4 py-4 font-semibold text-casa-text">{{ $transaction->transaction_number }}</td>
                                    <td class="px-4 py-4 text-casa-muted">{{ $transaction->customerProfile?->user?->name }}</td>
                                    <td class="px-4 py-4 text-casa-muted">{{ $transaction->service?->name ?: __('General service') }}</td>
                                    <td class="px-4 py-4 font-semibold text-casa-text">PHP {{ number_format((float) $transaction->amount, 2) }}</td>
                                    <td class="px-4 py-4"><x-status-badge>{{ ucfirst($transaction->payment_status) }}</x-status-badge></td>
                                    <td class="px-4 py-4">
                                        <a href="{{ route('admin.transactions.show', $transaction) }}" class="font-bold text-casa-primary hover:text-casa-primary-dark">{{ __('Open') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>
                    <div class="mt-5">{{ $transactions->links() }}</div>
                @endif
            </div>
        </x-app-card>
    </div>
</x-app-layout>
