<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Feedback detail') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ $feedback->customerProfile?->user?->name }}</h1>
            <p class="mt-2 text-sm text-casa-muted">{{ $feedback->service?->name }}</p>
        </div>

        <a href="{{ route('admin.feedback.index') }}" class="casa-button-secondary">{{ __('All feedback') }}</a>
    </x-slot>

    <x-app-card>
        <x-stat-strip :items="[
            ['label' => __('Rating'), 'value' => $feedback->rating.'/5', 'meta' => __('Customer score'), 'tone' => 'gold'],
            ['label' => __('Sentiment'), 'value' => ucfirst($feedback->sentiment_label), 'meta' => __('English, Tagalog, and Taglish rules'), 'tone' => 'green'],
            ['label' => __('Score'), 'value' => number_format((float) $feedback->sentiment_score, 2), 'meta' => __('Analysis ').($feedback->sentiment_analysis_version ?? '1.0'), 'tone' => 'brown'],
        ]" />
        <div class="mt-6 rounded-2xl bg-casa-bg p-5">
            <p class="whitespace-pre-line text-sm leading-7 text-casa-muted">{{ $feedback->comment ?: __('No written comment.') }}</p>
        </div>
        @if ($feedback->topics->isNotEmpty())
            <div class="mt-5 flex flex-wrap gap-2">
                @foreach ($feedback->topics as $topic)
                    <span class="rounded-full bg-casa-bg px-3 py-2 text-xs font-bold text-casa-muted">{{ str($topic->topic_key)->replace('_', ' ')->title() }} · {{ ucfirst($topic->polarity) }}</span>
                @endforeach
            </div>
        @endif
        @if ($feedback->sentiment_evidence)
            <p class="mt-4 text-xs text-casa-muted">{{ __('Classification evidence: ') }}{{ implode(', ', array_filter([$feedback->sentiment_evidence['rating_label'] ?? null, isset($feedback->sentiment_evidence['matched_words']) ? $feedback->sentiment_evidence['matched_words'].' matched terms' : null])) }}</p>
        @endif
    </x-app-card>
</x-app-layout>
