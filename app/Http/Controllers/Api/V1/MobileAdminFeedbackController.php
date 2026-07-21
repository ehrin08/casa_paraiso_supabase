<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\MobileStaffFeedbackResource;
use App\Models\Feedback;
use App\Models\FeedbackAnnotation;
use App\Models\FeedbackSentimentRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MobileAdminFeedbackController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sentiment' => ['nullable', Rule::in(Feedback::SENTIMENT_LABELS)], 'q' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'], 'topic' => ['nullable', 'string', 'max:64'],
            'source' => ['nullable', Rule::in(['rules', 'model', 'reviewed'])],
            'review_state' => ['nullable', Rule::in(['unreviewed', 'needs_review', 'reviewed'])],
            'confidence_min' => ['nullable', 'numeric', 'between:0,1'],
        ]);
        $search = trim((string) ($data['q'] ?? ''));
        $to = Carbon::parse($data['date_to'] ?? now()->toDateString())->endOfDay();
        $from = Carbon::parse($data['date_from'] ?? $to->copy()->subDays(29)->toDateString())->startOfDay();
        $feedback = Feedback::query()->with(['customerProfile.user', 'service', 'appointment', 'topics', 'sentimentRuns', 'annotations'])
            ->when(! empty($data['sentiment']), fn ($query) => $query->where('sentiment_label', $data['sentiment']))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('comment', 'like', "%{$search}%")->orWhereHas('customerProfile.user', fn ($user) => $user->where('name', 'like', "%{$search}%"))->orWhereHas('service', fn ($service) => $service->where('name', 'like', "%{$search}%"))))
            ->whereBetween('submitted_at', [$from, $to])
            ->when(! empty($data['service_id']), fn ($query) => $query->where('service_id', $data['service_id']))
            ->when(! empty($data['topic']), fn ($query) => $query->whereHas('topics', fn ($topics) => $topics->where('topic_key', $data['topic'])))
            ->when(! empty($data['source']), fn ($query) => $query->where('sentiment_source', $data['source']))
            ->when(isset($data['confidence_min']), fn ($query) => $query->where('sentiment_confidence', '>=', $data['confidence_min']))
            ->when(($data['review_state'] ?? null) === 'reviewed', fn ($query) => $query->whereHas('annotations', fn ($annotations) => $annotations->where('status', 'adjudicated')))
            ->when(($data['review_state'] ?? null) === 'unreviewed', fn ($query) => $query->whereDoesntHave('annotations'))
            ->when(($data['review_state'] ?? null) === 'needs_review', fn ($query) => $query->where(function ($query): void { $query->where('sentiment_source', 'rules')->orWhere('sentiment_confidence', '<', (float) config('sentiment.model_threshold', 0.8)); }))
            ->latest('submitted_at')->paginate((int) config('casa.pagination.per_page', 15))->withQueryString();

        $summaryQuery = Feedback::query()->whereBetween('submitted_at', [$from, $to]);
        $total = (clone $summaryQuery)->count();
        $positive = (clone $summaryQuery)->where('sentiment_label', Feedback::SENTIMENT_POSITIVE)->count();
        $negative = (clone $summaryQuery)->where('sentiment_label', Feedback::SENTIMENT_NEGATIVE)->count();
        $previousFrom = $from->copy()->subDays($from->diffInDays($to) + 1);
        $previousTo = $from->copy()->subSecond();
        $previousTotal = Feedback::query()->whereBetween('submitted_at', [$previousFrom, $previousTo])->count();
        $modelRuns = FeedbackSentimentRun::query()->with('feedback')->where('source', 'model')->whereHas('feedback', fn ($query) => $query->whereBetween('submitted_at', [$from, $to]))->get();
        $reviewed = FeedbackAnnotation::query()->where('status', 'adjudicated')->whereHas('feedback', fn ($query) => $query->whereBetween('submitted_at', [$from, $to]))->count();
        $serviceBreakdown = (clone $summaryQuery)->selectRaw('service_id, COUNT(*) AS total, SUM(CASE WHEN sentiment_label = ? THEN 1 ELSE 0 END) AS negative', [Feedback::SENTIMENT_NEGATIVE])->with('service')->groupBy('service_id')->get()->sortByDesc(fn ($row) => [(int) $row->negative, (int) $row->total])->take(10)->values()->map(fn ($row): array => ['id' => $row->service_id, 'name' => $row->service?->name, 'total' => (int) $row->total, 'negative' => (int) $row->negative])->all();
        $topicBreakdown = DB::table('feedback_topics')->join('feedback', 'feedback.id', '=', 'feedback_topics.feedback_id')->whereBetween('feedback.submitted_at', [$from, $to])->selectRaw('topic_key, COUNT(*) AS total, SUM(CASE WHEN polarity = ? THEN 1 ELSE 0 END) AS negative', ['negative'])->groupBy('topic_key')->get()->sortByDesc(fn ($row) => [(int) $row->negative, (int) $row->total])->take(10)->values()->map(fn ($row): array => ['key' => $row->topic_key, 'total' => (int) $row->total, 'negative' => (int) $row->negative])->all();

        return response()->json([
            'data' => MobileStaffFeedbackResource::collection($feedback->getCollection())->resolve($request),
            'summary' => ['positive' => $positive, 'neutral' => max(0, $total - $positive - $negative), 'negative' => $negative],
            'overview' => ['total' => $total, 'positive_rate' => $total ? round($positive / $total * 100, 1) : 0.0, 'negative_rate' => $total ? round($negative / $total * 100, 1) : 0.0, 'previous_total' => $previousTotal, 'attention' => $negative, 'service_breakdown' => $serviceBreakdown, 'topic_breakdown' => $topicBreakdown, 'model_health' => ['model_runs' => $modelRuns->count(), 'disagreements' => $modelRuns->filter(fn ($run) => $run->feedback?->sentiment_label !== $run->label)->count(), 'reviewed' => $reviewed, 'average_confidence' => round((float) $modelRuns->avg('confidence'), 4)], 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString()],
            'meta' => $this->pagination($feedback),
        ])->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, Feedback $feedback): JsonResponse
    {
        $feedback->load(['customerProfile.user', 'service', 'appointment', 'topics', 'sentimentRuns', 'annotations']);

        return response()->json(['data' => (new MobileStaffFeedbackResource($feedback))->resolve($request)])->header('Cache-Control', 'no-store');
    }

    public function review(Request $request, Feedback $feedback): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', Rule::in(Feedback::SENTIMENT_LABELS)],
            'language' => ['required', Rule::in(['English', 'Tagalog', 'Taglish', 'mixed'])],
            'topics' => ['nullable', 'array', 'max:6'],
            'topics.*' => ['string', Rule::in(array_keys(config('sentiment.topics', [])))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($feedback, $request, $data): void {
            $topics = collect($data['topics'] ?? [])->map(fn (string $key): array => ['topic_key' => $key, 'polarity' => $data['label'] === Feedback::SENTIMENT_NEGATIVE ? 'negative' : 'positive', 'matched_terms' => ['reviewed']])->all();
            $feedback->annotations()->create(['reviewer_id' => $request->user()->id, 'label' => $data['label'], 'language' => $data['language'], 'topics' => $data['topics'] ?? [], 'status' => 'adjudicated', 'notes' => $data['notes'] ?? null, 'adjudicated_at' => now()]);
            $score = match ($data['label']) { Feedback::SENTIMENT_POSITIVE => 1.0, Feedback::SENTIMENT_NEGATIVE => -1.0, default => 0.0 };
            $feedback->forceFill(['sentiment_label' => $data['label'], 'sentiment_score' => $score, 'sentiment_source' => 'reviewed', 'sentiment_confidence' => 1.0, 'sentiment_evidence' => array_merge($feedback->sentiment_evidence ?? [], ['reviewed' => true])])->save();
            $feedback->topics()->delete();
            $feedback->topics()->createMany($topics);
            $feedback->sentimentRuns()->create(['source' => 'reviewed', 'classifier_version' => 'human-review-v1', 'label' => $data['label'], 'score' => $score, 'confidence' => 1.0, 'evidence' => ['language' => $data['language'], 'notes' => $data['notes'] ?? null], 'is_authoritative' => true]);
        });

        return response()->json(['message' => 'Feedback review saved.']);
    }

    private function pagination($paginator): array
    {
        return ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem()];
    }
}
