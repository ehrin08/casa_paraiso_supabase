<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Staff module') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Feedback') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">{{ __('View feedback related to your assigned appointments.') }}</p>
        </div>
    </x-slot>

    <x-app-card>
        @if ($feedback->isEmpty())
            <x-empty-state title="{{ __('No related feedback yet') }}" description="{{ __('Customer reviews for your completed appointments will appear here.') }}" />
        @else
            <x-table-shell>
                <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                    <tr>
                        <th class="px-4 py-3">{{ __('Customer') }}</th>
                        <th class="px-4 py-3">{{ __('Service') }}</th>
                        <th class="px-4 py-3">{{ __('Rating') }}</th>
                        <th class="px-4 py-3">{{ __('Sentiment') }}</th>
                        <th class="px-4 py-3">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-casa-border text-sm">
                    @foreach ($feedback as $item)
                        <tr>
                            <td class="px-4 py-4 font-semibold text-casa-text">{{ $item->customerProfile?->user?->name }}</td>
                            <td class="px-4 py-4 text-casa-muted">{{ $item->service?->name }}</td>
                            <td class="px-4 py-4 text-casa-muted">{{ $item->rating }}/5</td>
                            <td class="px-4 py-4"><x-status-badge>{{ ucfirst($item->sentiment_label) }}</x-status-badge></td>
                            <td class="px-4 py-4"><a href="{{ route('staff.feedback.show', $item) }}" class="font-bold text-casa-primary hover:text-casa-primary-dark">{{ __('Open') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table-shell>
            <div class="mt-5">{{ $feedback->links() }}</div>
        @endif
    </x-app-card>
</x-app-layout>
