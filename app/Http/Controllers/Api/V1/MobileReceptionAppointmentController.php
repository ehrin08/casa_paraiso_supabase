<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\AdminAppointmentCompletionRequest;
use App\Http\Requests\AdminAppointmentOutcomeRequest;
use App\Http\Requests\AdminAppointmentStoreRequest;
use App\Http\Requests\AdminAppointmentUpdateRequest;
use App\Http\Resources\Api\V1\MobileOperationalAppointmentResource;
use App\Http\Resources\Api\V1\MobileTransactionResource;
use App\Models\ApplicationSetting;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Transaction;
use App\Services\AppointmentAddons;
use App\Services\AppointmentCompletion;
use App\Services\AppointmentManagement;
use App\Services\AppointmentWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MobileReceptionAppointmentController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(Appointment::STATUSES)],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);
        $search = trim((string) ($data['q'] ?? ''));
        $appointments = Appointment::query()
            ->with($this->relations())
            ->when(! empty($data['status']), fn ($query) => $query->where('status', $data['status']))
            ->when(! empty($data['date']), fn ($query) => $query->whereDate('scheduled_start_at', $data['date']))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('appointment_number', 'like', "%{$search}%")
                ->orWhereHas('customerProfile.user', fn ($user) => $user->where('name', 'like', "%{$search}%"))))
            ->orderByDesc('scheduled_start_at')
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();

        return response()->json([
            'data' => MobileOperationalAppointmentResource::collection($appointments->getCollection())->resolve($request),
            'summary' => [
                'confirmed' => Appointment::query()->where('status', Appointment::STATUS_CONFIRMED)->count(),
                'completed' => Appointment::query()->where('status', Appointment::STATUS_COMPLETED)->count(),
                'cancelled' => Appointment::query()->where('status', Appointment::STATUS_CANCELLED)->count(),
            ],
            'meta' => $this->pagination($appointments),
        ])->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, Appointment $appointment): JsonResponse
    {
        $appointment->load($this->relations());

        return response()->json(['data' => (new MobileOperationalAppointmentResource($appointment))->resolve($request)])
            ->header('Cache-Control', 'no-store');
    }

    public function options(AppointmentAddons $addons): JsonResponse
    {
        return response()->json(['data' => [
            'customers' => CustomerProfile::query()->with('user')->orderBy('customer_code')->get()->map(fn (CustomerProfile $customer): array => [
                'id' => $customer->id,
                'customer_code' => $customer->customer_code,
                'name' => $customer->user?->name,
                'phone' => $customer->user?->phone,
            ])->values(),
            'services' => Service::query()->where('is_active', true)->orderBy('name')->get()->map(fn (Service $service): array => [
                'id' => $service->id,
                'name' => $service->name,
                'duration_minutes' => $service->duration_minutes,
                'price' => number_format((float) $service->price, 2, '.', ''),
            ])->values(),
            'addons' => $addons->catalog()->map(fn (array $addon): array => [
                ...$addon,
                'price' => number_format((float) $addon['price'], 2, '.', ''),
            ])->values(),
            'payment_statuses' => Transaction::PAYMENT_STATUSES,
            'payment_methods' => Transaction::PAYMENT_METHODS,
            'default_payment_method' => ApplicationSetting::current()->default_payment_method,
            'initial_start_at' => now(config('app.timezone'))->addDay()->setTime(13, 0)->toIso8601String(),
        ]])->header('Cache-Control', 'no-store');
    }

    public function availableTherapists(Request $request, AppointmentWorkflow $workflow): JsonResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'starts_at' => ['required', 'date'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'addon_codes' => ['nullable', 'array'],
            'addon_codes.*' => ['required', 'string', 'distinct'],
        ]);
        $appointment = ! empty($data['appointment_id']) ? Appointment::query()->findOrFail($data['appointment_id']) : null;
        $service = Service::query()->findOrFail($data['service_id']);
        $start = Carbon::parse($data['starts_at'])->timezone(config('app.timezone'));
        $addonCodes = $data['addon_codes'] ?? [];
        $workflow->assertBookableStart($start, $service, 'starts_at', true, $addonCodes);
        $end = $workflow->scheduledEnd($start, $service, $addonCodes);
        $therapists = StaffProfile::query()
            ->with(['user', 'services', 'weeklySchedules', 'scheduleExceptions'])
            ->where('is_bookable', true)
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->whereHas('services', fn ($query) => $query->whereKey($service->id))
            ->get()
            ->filter(fn (StaffProfile $staff) => $workflow->isStaffAvailable($staff, $service, $start, $end, $appointment))
            ->sortBy('user.name')
            ->values()
            ->map(fn (StaffProfile $staff): array => [
                'id' => $staff->id,
                'name' => $staff->user?->name,
                'specialization' => $staff->specialization,
            ]);

        return response()->json(['data' => $therapists])->header('Cache-Control', 'no-store');
    }

    public function store(AdminAppointmentStoreRequest $request, AppointmentManagement $management): JsonResponse
    {
        $appointment = $management->persist(new Appointment, $request->validated(), $request->user()->id);
        $appointment->load($this->relations());

        return response()->json([
            'data' => (new MobileOperationalAppointmentResource($appointment))->resolve($request),
            'message' => 'Appointment confirmed and added to the schedule.',
        ], 201)->header('Cache-Control', 'no-store');
    }

    public function update(AdminAppointmentUpdateRequest $request, Appointment $appointment, AppointmentManagement $management): JsonResponse
    {
        $appointment = $management->persist($appointment, $request->validated(), $request->user()->id);
        $appointment->load($this->relations());

        return response()->json([
            'data' => (new MobileOperationalAppointmentResource($appointment))->resolve($request),
            'message' => 'Appointment updated.',
        ])->header('Cache-Control', 'no-store');
    }

    public function outcome(AdminAppointmentOutcomeRequest $request, Appointment $appointment, AppointmentWorkflow $workflow): JsonResponse
    {
        if ($appointment->status !== Appointment::STATUS_CONFIRMED) {
            throw ValidationException::withMessages(['status' => __('Only a confirmed appointment can receive this outcome.')]);
        }

        $data = $request->validated();
        $appointment = $workflow->changeStatus($appointment, $data['status'], $request->user()->id, $data['reason'] ?? null);
        $appointment->load($this->relations());

        return response()->json([
            'data' => (new MobileOperationalAppointmentResource($appointment))->resolve($request),
            'message' => $data['status'] === Appointment::STATUS_CANCELLED ? 'Appointment cancelled.' : 'Appointment marked as no-show.',
        ])->header('Cache-Control', 'no-store');
    }

    public function complete(AdminAppointmentCompletionRequest $request, Appointment $appointment, AppointmentCompletion $completion): JsonResponse
    {
        $transaction = $completion->complete($appointment, $request->validated(), $request->user()->id);
        $transaction->load(['customerProfile.user', 'service', 'appointment', 'recorder']);

        return response()->json([
            'data' => (new MobileTransactionResource($transaction))->resolve($request),
            'message' => 'Service finished and payment recorded.',
        ], 201)->header('Cache-Control', 'no-store');
    }

    /** @return array<int, string> */
    private function relations(): array
    {
        return ['customerProfile.user', 'service', 'staffProfile.user', 'preferredStaffProfile.user', 'addons', 'transactions'];
    }

    /** @return array<string, int|null> */
    private function pagination($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(), 'total' => $paginator->total(),
            'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(),
        ];
    }
}
