<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\MobileStaffFeedbackResource;
use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobileStaffFeedbackController
{
    public function index(Request $request): JsonResponse
    {
        $staffId = $this->staffId($request);
        $data = $request->validate(['sentiment' => ['nullable', Rule::in(Feedback::SENTIMENT_LABELS)], 'q' => ['nullable', 'string', 'max:255']]);
        $search = trim((string) ($data['q'] ?? ''));
        $feedback = Feedback::query()->with($this->relations())
            ->whereHas('appointment', fn ($query) => $query->where('staff_profile_id', $staffId))
            ->when(! empty($data['sentiment']), fn ($query) => $query->where('sentiment_label', $data['sentiment']))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('comment', 'like', "%{$search}%")
                ->orWhereHas('customerProfile.user', fn ($user) => $user->where('name', 'like', "%{$search}%"))))
            ->latest('submitted_at')->paginate((int) config('casa.pagination.per_page', 15))->withQueryString();

        return response()->json(['data' => MobileStaffFeedbackResource::collection($feedback->getCollection())->resolve($request), 'meta' => $this->pagination($feedback)])
            ->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, Feedback $feedback): JsonResponse
    {
        abort_unless((int) $feedback->appointment?->staff_profile_id === $this->staffId($request), 403);
        $feedback->load($this->relations());

        return response()->json(['data' => (new MobileStaffFeedbackResource($feedback))->resolve($request)])->header('Cache-Control', 'no-store');
    }

    private function staffId(Request $request): int
    {
        abort_unless($request->user()->staffProfile, 403);

        return (int) $request->user()->staffProfile->id;
    }

    private function relations(): array
    {
        return ['customerProfile.user', 'service', 'appointment', 'topics', 'sentimentRuns', 'annotations'];
    }

    private function pagination($paginator): array
    {
        return ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem()];
    }
}
