<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StaffAppointmentCompletionRequest;
use App\Http\Resources\Api\V1\MobileOperationalAppointmentResource;
use App\Http\Resources\Api\V1\MobileTransactionResource;
use App\Models\Appointment;
use App\Services\AppointmentCompletion;
use App\Services\AppointmentWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MobileStaffAppointmentController
{
    public function index(Request $request): JsonResponse
    {
        $staffId = $this->staffId($request);
        $data = $request->validate([
            'status' => ['nullable', Rule::in(Appointment::STATUSES)],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);
        $search = trim((string) ($data['q'] ?? ''));
        $base = Appointment::query()->where('staff_profile_id', $staffId);
        $appointments = (clone $base)->with($this->relations())
            ->when(! empty($data['status']), fn ($query) => $query->where('status', $data['status']))
            ->when(! empty($data['date']), fn ($query) => $query->whereDate('scheduled_start_at', $data['date']))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('appointment_number', 'like', "%{$search}%")
                ->orWhereHas('customerProfile.user', fn ($user) => $user->where('name', 'like', "%{$search}%"))))
            ->orderByDesc('scheduled_start_at')->paginate((int) config('casa.pagination.per_page', 15))->withQueryString();

        return response()->json([
            'data' => MobileOperationalAppointmentResource::collection($appointments->getCollection())->resolve($request),
            'summary' => [
                'confirmed' => (clone $base)->where('status', Appointment::STATUS_CONFIRMED)->count(),
                'completed' => (clone $base)->where('status', Appointment::STATUS_COMPLETED)->count(),
                'no_show' => (clone $base)->where('status', Appointment::STATUS_NO_SHOW)->count(),
            ],
            'meta' => $this->pagination($appointments),
        ])->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorizeAssigned($request, $appointment);
        $appointment->load($this->relations());

        return response()->json(['data' => (new MobileOperationalAppointmentResource($appointment))->resolve($request)])
            ->header('Cache-Control', 'no-store');
    }

    public function outcome(Request $request, Appointment $appointment, AppointmentWorkflow $workflow): JsonResponse
    {
        $this->authorizeAssigned($request, $appointment);
        $data = $request->validate(['status' => ['required', Rule::in([Appointment::STATUS_NO_SHOW])], 'reason' => ['nullable', 'string', 'max:1000']]);
        if ($appointment->status !== Appointment::STATUS_CONFIRMED) {
            throw ValidationException::withMessages(['status' => __('Only a confirmed appointment can receive this outcome.')]);
        }
        $appointment = $workflow->changeStatus($appointment, $data['status'], $request->user()->id, $data['reason'] ?? null);
        $appointment->load($this->relations());

        return response()->json(['data' => (new MobileOperationalAppointmentResource($appointment))->resolve($request), 'message' => 'Appointment marked as no-show.'])
            ->header('Cache-Control', 'no-store');
    }

    public function complete(StaffAppointmentCompletionRequest $request, Appointment $appointment, AppointmentCompletion $completion): JsonResponse
    {
        $this->authorizeAssigned($request, $appointment);
        $transaction = $completion->complete($appointment, $request->validated(), $request->user()->id);
        $transaction->load(['customerProfile.user', 'service', 'appointment', 'recorder']);

        return response()->json(['data' => (new MobileTransactionResource($transaction))->resolve($request), 'message' => 'Service finished and payment recorded.'], 201)
            ->header('Cache-Control', 'no-store');
    }

    private function staffId(Request $request): int
    {
        abort_unless($request->user()->staffProfile, 403);

        return (int) $request->user()->staffProfile->id;
    }

    private function authorizeAssigned(Request $request, Appointment $appointment): void
    {
        abort_unless((int) $appointment->staff_profile_id === $this->staffId($request), 403);
    }

    private function relations(): array
    {
        return ['customerProfile.user', 'service', 'staffProfile.user', 'preferredStaffProfile.user', 'addons', 'transactions'];
    }

    private function pagination($paginator): array
    {
        return ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem()];
    }
}
