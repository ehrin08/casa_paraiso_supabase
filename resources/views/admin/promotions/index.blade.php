<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Admin module') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Promotions') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Generate and review rule-based RFM promotion suggestions before any customer follow-up.') }}
            </p>
        </div>

        <form method="POST" action="{{ route('admin.promotions.generate') }}">
            @csrf
            <button type="submit" class="casa-button-primary">{{ __('Generate suggestions') }}</button>
        </form>
    </x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[18px] border border-casa-green/30 bg-casa-green/10 px-5 py-4 text-sm font-semibold text-casa-green">
                {{ session('status') === 'promotions-generated' ? trans_choice(':count promotion suggestion generated|:count promotion suggestions generated', session('generated_count', 0)) : __('Promotion suggestion updated.') }}
            </div>
        @endif

        <section class="grid gap-4 md:grid-cols-3">
            <x-metric-card label="Suggested" :value="$summary['suggested']" meta="Needs review" tone="gold" />
            <x-metric-card label="Applied" :value="$summary['applied']" meta="Marked used" tone="green" />
            <x-metric-card label="Dismissed" :value="$summary['dismissed']" meta="No action" tone="charcoal" />
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
            <x-app-card>
                <div class="flex flex-col gap-4 border-b border-casa-border pb-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="casa-section-label">{{ __('Suggestions') }}</p>
                        <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Review queue') }}</h2>
                    </div>
                    <form method="GET" action="{{ route('admin.promotions.index') }}" class="flex flex-col gap-3 sm:flex-row">
                        <select name="status" class="casa-input">
                            <option value="">{{ __('All statuses') }}</option>
                            @foreach (\App\Models\PromotionSuggestion::STATUSES as $option)
                                <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="casa-button-secondary">{{ __('Filter') }}</button>
                    </form>
                </div>

                <div class="mt-5">
                    @if ($suggestions->isEmpty())
                        <x-empty-state title="{{ __('No promotion suggestions yet') }}" description="{{ __('Run generation after paid completed transactions exist.') }}" />
                    @else
                        <x-table-shell>
                            <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                                <tr>
                                    <th class="px-4 py-3">{{ __('Customer') }}</th>
                                    <th class="px-4 py-3">{{ __('Segment') }}</th>
                                    <th class="px-4 py-3">{{ __('RFM') }}</th>
                                    <th class="px-4 py-3">{{ __('Status') }}</th>
                                    <th class="px-4 py-3">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-casa-border text-sm">
                                @foreach ($suggestions as $suggestion)
                                    <tr>
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
