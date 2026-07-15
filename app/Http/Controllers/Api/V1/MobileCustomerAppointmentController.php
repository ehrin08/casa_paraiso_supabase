<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\MobileAppointmentResource;
use App\Models\Appointment;
use App\Services\AppointmentWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class MobileCustomerAppointmentController
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(Appointment::STATUSES)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);
        $customerProfileId = $request->user()->customerProfile?->id;

        if (! $customerProfileId) {
            return $this->error('CUSTOMER_PROFILE_REQUIRED', 'This account does not have a customer profile.', 403);
        }

        $dateFrom = isset($filters['date_from'])
            ? Carbon::createFromFormat('Y-m-d', $filters['date_from'])->startOfDay()
            : null;
        $dateTo = isset($filters['date_to'])
            ? Carbon::createFromFormat('Y-m-d', $filters['date_to'])->endOfDay()
            : null;
        $appointmentDate = 'COALESCE(scheduled_start_at, requested_start_at)';
        $baseQuery = Appointment::query()->where('customer_profile_id', $customerProfileId);

        $summary = [
            'upcoming' => (clone $baseQuery)
                ->where('status', Appointment::STATUS_CONFIRMED)
                ->where('scheduled_start_at', '>=', now())
                ->count(),
            'completed' => (clone $baseQuery)->where('status', Appointment::STATUS_COMPLETED)->count(),
            'cancelled' => (clone $baseQuery)->where('status', Appointment::STATUS_CANCELLED)->count(),
        ];

        $appointments = $baseQuery
            ->with(['service', 'staffProfile.user', 'preferredStaffProfile.user', 'promotionSuggestion', 'addons', 'feedback'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($dateFrom, fn ($query) => $query->whereRaw("{$appointmentDate} >= ?", [$dateFrom]))
            ->when($dateTo, fn ($query) => $query->whereRaw("{$appointmentDate} <= ?", [$dateTo]))
            ->orderByRaw("CASE WHEN status = ? AND {$appointmentDate} >= ? THEN 0 ELSE 1 END", [Appointment::STATUS_CONFIRMED, now()])
            ->orderByRaw("CASE WHEN status = ? AND {$appointmentDate} >= ? THEN {$appointmentDate} END ASC", [Appointment::STATUS_CONFIRMED, now()])
            ->orderByRaw("CASE WHEN status = ? AND {$appointmentDate} >= ? THEN NULL ELSE {$appointmentDate} END DESC", [Appointment::STATUS_CONFIRMED, now()])
            ->orderByDesc('id')
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();

        return response()->json([
            'data' => MobileAppointmentResource::collection($appointments->items())->resolve($request),
            'summary' => $summary,
            'meta' => [
                'current_page' => $appointments->currentPage(),
                'last_page' => $appointments->lastPage(),
                'per_page' => $appointments->perPage(),
                'total' => $appointments->total(),
                'from' => $appointments->firstItem(),
                'to' => $appointments->lastItem(),
            ],
        ])->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, Appointment $appointment): JsonResponse
    {
        if (! $this->owns($request, $appointment)) {
            return $this->error('APPOINTMENT_FORBIDDEN', 'You may only view your own appointments.', 403);
        }

        $appointment->load(['service', 'staffProfile.user', 'preferredStaffProfile.user', 'promotionSuggestion', 'addons', 'feedback']);

        return response()->json([
            'data' => (new MobileAppointmentResource($appointment))->resolve($request),
        ])->header('Cache-Control', 'no-store');
    }

    public function cancel(Request $request, Appointment $appointment, AppointmentWorkflow $workflow): JsonResponse
    {
        if (! $this->owns($request, $appointment)) {
            return $this->error('APPOINTMENT_FORBIDDEN', 'You may only cancel your own appointments.', 403);
        }

        if ($appointment->status !== Appointment::STATUS_CONFIRMED) {
            return $this->error('APPOINTMENT_NOT_CANCELLABLE', 'Only an upcoming confirmed appointment can be cancelled.', 409);
        }

        if (! $appointment->scheduled_start_at || ! $appointment->scheduled_start_at->isFuture()) {
            return $this->error('APPOINTMENT_START_PASSED', 'This appointment can no longer be cancelled because its start time has passed.', 409);
        }

        $appointment = $workflow->changeStatus(
            $appointment,
            Appointment::STATUS_CANCELLED,
            $request->user()->id,
            __('Cancelled by customer from mobile'),
        );
        $appointment->load(['service', 'staffProfile.user', 'preferredStaffProfile.user', 'promotionSuggestion', 'addons', 'feedback']);

        return response()->json([
            'data' => (new MobileAppointmentResource($appointment))->resolve($request),
            'message' => 'Appointment cancelled.',
        ])->header('Cache-Control', 'no-store');
    }

    private function owns(Request $request, Appointment $appointment): bool
    {
        return (int) $appointment->customer_profile_id === (int) ($request->user()->customerProfile?->id ?? 0);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => compact('code', 'message')], $status)
            ->header('Cache-Control', 'no-store');
    }
}
