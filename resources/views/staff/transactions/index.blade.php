<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Staff module') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Transactions') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">{{ __('Record payments from your confirmed or completed appointments.') }}</p>
        </div>

        <a href="{{ route('staff.transactions.create') }}" class="casa-button-primary">{{ __('Record payment') }}</a>
    </x-slot>

    <x-app-card>
        @if ($transactions->isEmpty())
            <x-empty-state title="{{ __('No transactions yet') }}" description="{{ __('Payments you record or payments linked to your appointments will appear here.') }}" />
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
                            <td class="px-4 py-4 text-casa-muted">{{ $transaction->service?->name }}</td>
                            <td class="px-4 py-4 font-semibold text-casa-text">PHP {{ number_format((float) $transaction->amount, 2) }}</td>
                            <td class="px-4 py-4"><x-status-badge>{{ ucfirst($transaction->payment_status) }}</x-status-badge></td>
                            <td class="px-4 py-4"><a href="{{ route('staff.transactions.show', $transaction) }}" class="font-bold text-casa-primary hover:text-casa-primary-dark">{{ __('Open') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table-shell>
            <div class="mt-5">{{ $transactions->links() }}</div>
        @endif
    </x-app-card>
</x-app-layout>
