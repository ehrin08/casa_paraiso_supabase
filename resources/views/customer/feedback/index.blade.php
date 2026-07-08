<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Customer lounge') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Feedback') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">{{ __('Submit feedback after completed appointments and review your past comments.') }}</p>
        </div>
        <a href="{{ route('customer.feedback.create') }}" class="casa-button-primary">{{ __('Submit feedback') }}</a>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="space-y-6">
            <x-app-card>
                <x-list-toolbar eyebrow="{{ __('My reviews') }}" title="{{ __('Feedback history') }}" :count="$feedback->total()" :reset-url="route('customer.feedback.index')">
                    <form method="GET" action="{{ route('customer.feedback.index') }}" class="casa-filter-grid sm:grid-cols-[minmax(10rem,1fr)_auto_auto]">
                        <input type="hidden" name="sort" value="{{ $sort }}">
                        <input type="hidden" name="direction" value="{{ $direction }}">
                        <input type="search" name="q" value="{{ $search }}" class="casa-input" placeholder="{{ __('Search service or comment') }}" aria-label="{{ __('Search feedback') }}">
                        <select name="sentiment_label" class="casa-input">
                            <option value="">{{ __('All sentiment') }}</option>
                            @foreach (\App\Models\Feedback::SENTIMENT_LABELS as $option)
                                <option value="{{ $option }}" @selected($sentiment === $option)>{{ ucfirst($option) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="casa-button-secondary">{{ __('Filter') }}</button>
                    </form>
                </x-list-toolbar>
                <div class="mt-5">
                    @if ($feedback->isEmpty())
                        <x-empty-state title="{{ __('No feedback yet') }}" description="{{ __('Completed appointments without feedback can be reviewed from this page.') }}" />
                    @else
                        <x-table-shell>
                            <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                                <tr>
                                    <x-sortable-th sort="service">{{ __('Service') }}</x-sortable-th>
                                    <x-sortable-th sort="rating">{{ __('Rating') }}</x-sortable-th>
                                    <x-sortable-th sort="sentiment">{{ __('Sentiment') }}</x-sortable-th>
                                    <x-sortable-th sort="submitted">{{ __('Submitted') }}</x-sortable-th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-casa-border text-sm">
                                @foreach ($feedback as $item)
                                    <tr class="casa-table-row">
                                        <td class="px-4 py-4 font-semibold text-casa-text">{{ $item->service?->name }}</td>
                                        <td class="px-4 py-4 text-casa-muted">{{ $item->rating }}/5</td>
                                        <td class="px-4 py-4"><x-status-badge>{{ ucfirst($item->sentiment_label) }}</x-status-badge></td>
                                        <td class="px-4 py-4 text-casa-muted">{{ $item->submitted_at?->format('M d, Y') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-table-shell>
                        <div class="mt-5">{{ $feedback->links() }}</div>
                    @endif
                </div>
            </x-app-card>
        </section>

        <aside class="space-y-4">
            <x-app-card>
                <p class="casa-section-label">{{ __('Eligible visits') }}</p>
                <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Completed appointments') }}</h2>
                <div class="mt-5 space-y-3">
                    @forelse ($completedAppointments->whereNull('feedback') as $appointment)
                        <a href="{{ route('customer.feedback.create', ['appointment_id' => $appointment->id]) }}" class="block rounded-2xl border border-casa-border bg-casa-bg p-4 text-sm font-semibold text-casa-text hover:border-casa-gold">
                            {{ $appointment->service?->name }} · {{ $appointment->completed_at?->format('M d, Y') }}
                        </a>
                    @empty
                        <p class="text-sm leading-6 text-casa-muted">{{ __('No completed appointments are waiting for feedback.') }}</p>
                    @endforelse
                </div>
            </x-app-card>
        </aside>
    </div>
</x-app-layout>
