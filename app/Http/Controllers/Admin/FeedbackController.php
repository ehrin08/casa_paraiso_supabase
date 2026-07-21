<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesIndexSorting;
use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FeedbackController extends Controller
{
    use HandlesIndexSorting;

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'topic' => ['nullable', 'string', 'max:64'],
        ]);
        $sentiment = (string) $request->query('sentiment_label');
        $search = trim((string) $request->query('q'));
        $dateTo = Carbon::parse($validated['date_to'] ?? now()->toDateString())->endOfDay();
        $dateFrom = Carbon::parse($validated['date_from'] ?? $dateTo->copy()->subDays(29)->toDateString())->startOfDay();
        $serviceId = (int) ($validated['service_id'] ?? 0) ?: null;
        $topic = (string) ($validated['topic'] ?? '');
        $sorts = [
            'customer' => 'feedback_customers.name',
            'service' => 'feedback_services.name',
            'rating' => 'feedback.rating',
            'sentiment' => 'feedback.sentiment_label',
            'submitted' => 'feedback.submitted_at',
        ];
        $sort = $this->indexSort($request, $sorts, 'submitted');
        $direction = $this->indexDirection($request, 'desc');

        $feedback = Feedback::query()
            ->with(['customerProfile.user', 'service', 'appointment', 'topics'])
            ->leftJoin('customer_profiles as feedback_customer_profiles', 'feedback_customer_profiles.id', '=', 'feedback.customer_profile_id')
            ->leftJoin('users as feedback_customers', 'feedback_customers.id', '=', 'feedback_customer_profiles.user_id')
            ->leftJoin('services as feedback_services', 'feedback_services.id', '=', 'feedback.service_id')
            ->select('feedback.*')
            ->when(in_array($sentiment, Feedback::SENTIMENT_LABELS, true), fn ($query) => $query->where('feedback.sentiment_label', $sentiment))
            ->whereBetween('feedback.submitted_at', [$dateFrom, $dateTo])
            ->when($serviceId, fn ($query) => $query->where('feedback.service_id', $serviceId))
            ->when($topic !== '', fn ($query) => $query->whereHas('topics', fn ($topics) => $topics->where('topic_key', $topic)))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('feedback_customers.name', 'like', "%{$search}%")
                    ->orWhere('feedback_services.name', 'like', "%{$search}%")
                    ->orWhere('feedback.comment', 'like', "%{$search}%");
            }))
            ->orderBy($sorts[$sort], $direction)
            ->orderByDesc('feedback.submitted_at')
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();
        $summary = Feedback::query()->whereBetween('submitted_at', [$dateFrom, $dateTo])
            ->selectRaw('SUM(CASE WHEN sentiment_label = ? THEN 1 ELSE 0 END) AS positive', [Feedback::SENTIMENT_POSITIVE])
            ->selectRaw('SUM(CASE WHEN sentiment_label = ? THEN 1 ELSE 0 END) AS neutral', [Feedback::SENTIMENT_NEUTRAL])
            ->selectRaw('SUM(CASE WHEN sentiment_label = ? THEN 1 ELSE 0 END) AS negative', [Feedback::SENTIMENT_NEGATIVE])
            ->first();

        $previousFrom = $dateFrom->copy()->subDays($dateFrom->diffInDays($dateTo) + 1);
        $previousTo = $dateFrom->copy()->subSecond();
        $previousTotal = Feedback::query()->whereBetween('submitted_at', [$previousFrom, $previousTo])->count();
        $total = $feedback->total();
        $positive = (int) ($summary?->positive ?? 0);
        $negative = (int) ($summary?->negative ?? 0);
        $overview = [
            'total' => $total,
            'positive' => $positive,
            'neutral' => (int) ($summary?->neutral ?? 0),
            'negative' => $negative,
            'positive_rate' => $total ? round($positive / $total * 100, 1) : 0.0,
            'negative_rate' => $total ? round($negative / $total * 100, 1) : 0.0,
            'previous_total' => $previousTotal,
            'attention' => $negative,
            'service_breakdown' => Feedback::query()->whereBetween('submitted_at', [$dateFrom, $dateTo])->selectRaw('service_id, COUNT(*) AS total, SUM(CASE WHEN sentiment_label = ? THEN 1 ELSE 0 END) AS negative', [Feedback::SENTIMENT_NEGATIVE])->with('service')->groupBy('service_id')->get()->sortByDesc(fn ($row) => [(int) $row->negative, (int) $row->total])->take(5)->values(),
            'topic_breakdown' => DB::table('feedback_topics')->join('feedback', 'feedback.id', '=', 'feedback_topics.feedback_id')->whereBetween('feedback.submitted_at', [$dateFrom, $dateTo])->selectRaw('topic_key, COUNT(*) AS total, SUM(CASE WHEN polarity = ? THEN 1 ELSE 0 END) AS negative', ['negative'])->groupBy('topic_key')->get()->sortByDesc(fn ($row) => [(int) $row->negative, (int) $row->total])->take(6)->values(),
        ];

        return view('admin.feedback.index', [
            'feedback' => $feedback,
            'sentiment' => $sentiment,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
            'summary' => ['positive' => (int) $summary?->positive, 'neutral' => (int) $summary?->neutral, 'negative' => (int) $summary?->negative],
            'overview' => $overview,
            'dateFrom' => $dateFrom->toDateString(), 'dateTo' => $dateTo->toDateString(), 'serviceId' => $serviceId, 'topic' => $topic,
            'services' => Service::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Feedback $feedback): View
    {
        $feedback->load(['customerProfile.user', 'service', 'appointment', 'topics']);

        return view('admin.feedback.show', [
            'feedback' => $feedback,
        ]);
    }
}
