<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Admin module') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Promotions') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Generate and review rule-based RFM promotion suggestions before any customer follow-up.') }}
            </p>
        </div>

        <x-confirm-action
            :action="route('admin.promotions.generate')"
            label="{{ __('Generate suggestions') }}"
            confirm-title="{{ __('Generate promotion suggestions?') }}"
            confirm-message="{{ __('The system will scan customer transaction history and create any new rule-based RFM suggestions that are not already in the review queue.') }}"
            confirm-button="{{ __('Generate') }}"
            button-class="casa-button-primary"
        />
    </x-slot>

    <div class="space-y-6">
        <section class="grid gap-4 md:grid-cols-3">
            <x-metric-card label="Suggested" :value="$summary['suggested']" meta="Needs review" tone="gold" />
            <x-metric-card label="Applied" :value="$summary['applied']" meta="Marked used" tone="green" />
            <x-metric-card label="Dismissed" :value="$summary['dismissed']" meta="No action" tone="charcoal" />
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
            <x-app-card>
                <x-list-toolbar eyebrow="{{ __('Suggestions') }}" title="{{ __('Review queue') }}" :count="$suggestions->total()" :reset-url="route('admin.promotions.index')">
                    <form method="GET" action="{{ route('admin.promotions.index') }}" class="casa-filter-grid sm:grid-cols-[minmax(12rem,1fr)_auto_auto] lg:min-w-[42rem]">
                        <input type="hidden" name="sort" value="{{ $sort }}">
                        <input type="hidden" name="direction" value="{{ $direction }}">
                        <input type="search" name="q" value="{{ $search }}" class="casa-input" placeholder="{{ __('Search customer, segment, offer') }}" aria-label="{{ __('Search promotions') }}">
                        <select name="status" class="casa-input">
                            <option value="">{{ __('All statuses') }}</option>
                            @foreach (\App\Models\PromotionSuggestion::STATUSES as $option)
                                <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="casa-button-secondary">{{ __('Filter') }}</button>
                    </form>
                </x-list-toolbar>

                <div class="mt-5">
                    @if ($suggestions->isEmpty())
                        <x-empty-state title="{{ __('No promotion suggestions yet') }}" description="{{ __('Run generation after paid completed transactions exist.') }}" />
                    @else
                        <x-table-shell>
                            <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                                <tr>
                                    <x-sortable-th sort="customer">{{ __('Customer') }}</x-sortable-th>
                                    <x-sortable-th sort="segment">{{ __('Segment') }}</x-sortable-th>
                                    <x-sortable-th sort="monetary">{{ __('RFM') }}</x-sortable-th>
                                    <x-sortable-th sort="status">{{ __('Status') }}</x-sortable-th>
                                    <th class="px-4 py-3">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-casa-border text-sm">
                                @foreach ($suggestions as $suggestion)
                                    <tr class="casa-table-row">
                                        <td class="px-4 py-4 font-semibold text-casa-text">{{ $suggestion->customerProfile?->user?->name }}</td>
                                        <td class="px-4 py-4 text-casa-muted">{{ $suggestion->rfmSegment?->name ?: __('Unsegmented') }}</td>
                                        <td class="px-4 py-4 text-casa-muted">R{{ $suggestion->recency_days ?? 'N/A' }} F{{ $suggestion->frequency_count ?? 0 }} M{{ number_format((float) $suggestion->monetary_total, 2) }}</td>
                                        <td class="px-4 py-4"><x-status-badge>{{ ucfirst($suggestion->status) }}</x-status-badge></td>
                                        <td class="px-4 py-4"><a href="{{ route('admin.promotions.show', $suggestion) }}" class="font-bold text-casa-primary hover:text-casa-primary-dark">{{ __('Review') }}</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-table-shell>
                        <div class="mt-5">{{ $suggestions->links() }}</div>
                    @endif
                </div>
            </x-app-card>

            <aside class="space-y-4">
                <x-app-card>
                    <p class="casa-section-label">{{ __('RFM segments') }}</p>
                    <div class="mt-5 space-y-3">
                        @foreach ($segments as $segment)
                            <div class="rounded-2xl border border-casa-border bg-casa-bg p-4">
                                <p class="font-bold text-casa-text">{{ $segment->name }}</p>
                                <p class="mt-1 text-xs text-casa-muted">{{ trans_choice(':count rule|:count rules', $segment->promotion_rules_count) }} · {{ trans_choice(':count suggestion|:count suggestions', $segment->promotion_suggestions_count) }}</p>
                            </div>
                        @endforeach
                    </div>
                </x-app-card>
            </aside>
        </section>
    </div>
</x-app-layout>
