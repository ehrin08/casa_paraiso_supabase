<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\MobileStaffFeedbackResource;
use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobileAdminFeedbackController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['sentiment' => ['nullable', Rule::in(Feedback::SENTIMENT_LABELS)], 'q' => ['nullable', 'string', 'max:255']]);
        $search = trim((string) ($data['q'] ?? ''));
        $feedback = Feedback::query()->with(['customerProfile.user', 'service', 'appointment'])
            ->when(! empty($data['sentiment']), fn ($query) => $query->where('sentiment_label', $data['sentiment']))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('comment', 'like', "%{$search}%")->orWhereHas('customerProfile.user', fn ($user) => $user->where('name', 'like', "%{$search}%"))->orWhereHas('service', fn ($service) => $service->where('name', 'like', "%{$search}%"))))
            ->latest('submitted_at')->paginate((int) config('casa.pagination.per_page', 15))->withQueryString();

        return response()->json([
            'data' => MobileStaffFeedbackResource::collection($feedback->getCollection())->resolve($request),
            'summary' => ['positive' => Feedback::query()->where('sentiment_label', Feedback::SENTIMENT_POSITIVE)->count(), 'neutral' => Feedback::query()->where('sentiment_label', Feedback::SENTIMENT_NEUTRAL)->count(), 'negative' => Feedback::query()->where('sentiment_label', Feedback::SENTIMENT_NEGATIVE)->count()],
            'meta' => $this->pagination($feedback),
        ])->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, Feedback $feedback): JsonResponse
    {
        $feedback->load(['customerProfile.user', 'service', 'appointment']);

        return response()->json(['data' => (new MobileStaffFeedbackResource($feedback))->resolve($request)])->header('Cache-Control', 'no-store');
    }

    private function pagination($paginator): array
    {
        return ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem()];
    }
}
