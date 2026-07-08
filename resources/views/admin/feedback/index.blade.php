<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Admin module') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Feedback') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Review ratings, comments, and simple sentiment labels from completed visits.') }}
            </p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="grid gap-4 md:grid-cols-3">
            <x-metric-card label="Positive" :value="$summary['positive']" meta="Satisfied feedback" tone="green" />
            <x-metric-card label="Neutral" :value="$summary['neutral']" meta="Middle rating" tone="gold" />
            <x-metric-card label="Negative" :value="$summary['negative']" meta="Needs attention" tone="charcoal" />
        </section>

        <x-app-card>
            <div class="flex flex-col gap-4 border-b border-casa-border pb-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="casa-section-label">{{ __('Reviews') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Customer feedback') }}</h2>
                </div>
                <form method="GET" action="{{ route('admin.feedback.index') }}" class="flex flex-col gap-3 sm:flex-row">
                    <select name="sentiment_label" class="casa-input">
                        <option value="">{{ __('All sentiment') }}</option>
                        @foreach (\App\Models\Feedback::SENTIMENT_LABELS as $option)
                            <option value="{{ $option }}" @selected($sentiment === $option)>{{ ucfirst($option) }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="casa-button-secondary">{{ __('Filter') }}</button>
                </form>
            </div>

            <div class="mt-5">
                @if ($feedback->isEmpty())
                    <x-empty-state title="{{ __('No feedback yet') }}" description="{{ __('Customer feedback appears after completed appointments.') }}" />
                @else
                    <x-table-shell>
                        <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                            <tr>
                                <th class="px-4 py-3">{{ __('Customer') }}</th>
                                <th class="px-4 py-3">{{ __('Service') }}</th>
                                <th class="px-4 py-3">{{ __('Rating') }}</th>
                                <th class="px-4 py-3">{{ __('Sentiment') }}</th>
                                <th class="px-4 py-3">{{ __('Submitted') }}</th>
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
                                    <td class="px-4 py-4 text-casa-muted">{{ $item->submitted_at?->format('M d, Y') }}</td>
                                    <td class="px-4 py-4"><a href="{{ route('admin.feedback.show', $item) }}" class="font-bold text-casa-primary hover:text-casa-primary-dark">{{ __('Open') }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>
                    <div class="mt-5">{{ $feedback->links() }}</div>
                @endif
            </div>
        </x-app-card>
    </div>
</x-app-layout>
