<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\MobileFeedbackResource;
use App\Models\Appointment;
use App\Models\Feedback;
use App\Services\HybridSentimentClassifier;
use App\Services\FeedbackSentimentUpdater;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MobileCustomerFeedbackController
{
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user()->customerProfile;

        if (! $customer) {
            return $this->missingCustomerProfile();
        }

        $eligibleAppointments = Appointment::query()
            ->with(['service', 'staffProfile.user'])
            ->where('customer_profile_id', $customer->id)
            ->where('status', Appointment::STATUS_COMPLETED)
            ->whereDoesntHave('feedback')
            ->latest('completed_at')
            ->get()
            ->map(fn (Appointment $appointment): array => [
                'id' => $appointment->id,
                'appointment_number' => $appointment->appointment_number,
                'completed_at' => $this->timestamp($appointment->completed_at),
                'service' => $appointment->service ? [
                    'id' => $appointment->service->id,
                    'name' => $appointment->service->name,
                ] : null,
                'therapist' => $appointment->staffProfile ? [
                    'id' => $appointment->staffProfile->id,
                    'name' => $appointment->staffProfile->user?->name,
                ] : null,
            ])->values();

        $feedback = Feedback::query()
            ->with(['service', 'appointment'])
            ->where('customer_profile_id', $customer->id)
            ->latest('submitted_at')
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();

        return response()->json([
            'data' => MobileFeedbackResource::collection($feedback->getCollection())->resolve($request),
            'eligible_appointments' => $eligibleAppointments,
            'summary' => [
                'awaiting_feedback' => $eligibleAppointments->count(),
                'submitted' => $feedback->total(),
            ],
            'meta' => [
                'current_page' => $feedback->currentPage(),
                'last_page' => $feedback->lastPage(),
                'per_page' => $feedback->perPage(),
                'total' => $feedback->total(),
                'from' => $feedback->firstItem(),
                'to' => $feedback->lastItem(),
            ],
        ])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, HybridSentimentClassifier $classifier, FeedbackSentimentUpdater $updater): JsonResponse
    {
        $customer = $request->user()->customerProfile;

        if (! $customer) {
            return $this->missingCustomerProfile();
        }

        $data = $request->validate([
            'appointment_id' => ['required', 'integer', 'exists:appointments,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:5000'],
        ]);

        $feedback = DB::transaction(function () use ($data, $customer, $classifier, $updater): Feedback {
            $appointment = Appointment::query()
                ->where('customer_profile_id', $customer->id)
                ->whereKey($data['appointment_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($appointment->status !== Appointment::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'appointment_id' => __('Feedback is available only for completed appointments.'),
                ]);
            }

            if (Feedback::query()->where('appointment_id', $appointment->id)->exists()) {
                throw ValidationException::withMessages([
                    'appointment_id' => __('Feedback was already submitted for this appointment.'),
                ]);
            }

            $comment = filled($data['comment'] ?? null) ? trim((string) $data['comment']) : null;
            $sentiment = $classifier->classify((int) $data['rating'], $comment);

            $feedback = Feedback::query()->create([
                'appointment_id' => $appointment->id,
                'customer_profile_id' => $customer->id,
                'service_id' => $appointment->service_id,
                'rating' => $data['rating'],
                'comment' => $comment,
                'sentiment_label' => $sentiment['label'],
                'sentiment_score' => $sentiment['score'],
                'submitted_at' => now(),
            ]);

            return $updater->persist($feedback, $sentiment);
        });

        $feedback->load(['service', 'appointment']);

        return response()->json([
            'data' => (new MobileFeedbackResource($feedback))->resolve($request),
            'message' => 'Thank you. Your feedback was submitted.',
        ], 201)->header('Cache-Control', 'no-store');
    }

    private function missingCustomerProfile(): JsonResponse
    {
        return response()->json(['error' => [
            'code' => 'CUSTOMER_PROFILE_REQUIRED',
            'message' => 'This account does not have a customer profile.',
        ]], 403)->header('Cache-Control', 'no-store');
    }

    private function timestamp($value): ?string
    {
        return $value?->copy()->timezone(config('app.timezone'))->toIso8601String();
    }
}
