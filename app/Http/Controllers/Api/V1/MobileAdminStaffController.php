<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\StaffScheduleConflictException;
use App\Http\Requests\Admin\StaffRequest;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\StaffScheduleConflictGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MobileAdminStaffController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'bookable' => ['nullable', Rule::in(['yes', 'no'])],
        ]);
        $search = trim((string) ($data['q'] ?? ''));
        $staff = StaffProfile::query()->with(['user', 'services'])->withCount(['services', 'appointments'])
            ->join('users', 'users.id', '=', 'staff_profiles.user_id')->select('staff_profiles.*')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('users.name', 'like', "%{$search}%")->orWhere('users.email', 'like', "%{$search}%")
                ->orWhere('staff_profiles.position', 'like', "%{$search}%")->orWhere('staff_profiles.specialization', 'like', "%{$search}%")))
            ->when(($data['status'] ?? null) === 'active', fn ($query) => $query->where('users.is_active', true))
            ->when(($data['status'] ?? null) === 'inactive', fn ($query) => $query->where('users.is_active', false))
            ->when(($data['bookable'] ?? null) === 'yes', fn ($query) => $query->where('staff_profiles.is_bookable', true))
            ->when(($data['bookable'] ?? null) === 'no', fn ($query) => $query->where('staff_profiles.is_bookable', false))
            ->orderByDesc('users.is_active')->orderBy('users.name')
            ->paginate((int) config('casa.pagination.per_page', 15))->withQueryString();

        return response()->json([
            'data' => $staff->getCollection()->map(fn (StaffProfile $profile) => $this->summary($profile))->values(),
            'summary' => [
                'active' => User::query()->where('role', User::ROLE_STAFF)->where('is_active', true)->count(),
                'inactive' => User::query()->where('role', User::ROLE_STAFF)->where('is_active', false)->count(),
                'bookable' => StaffProfile::query()->where('is_bookable', true)->count(),
            ],
            'meta' => $this->pagination($staff),
        ])->header('Cache-Control', 'no-store');
    }

    public function options(Request $request): JsonResponse
    {
        return response()->json(['data' => [
            'can_create' => $request->user()->isSuperAdmin(),
            'staff_types' => StaffProfile::TYPES,
            'services' => Service::query()->where('is_active', true)->orderBy('name')->get()->map(fn (Service $service) => ['id' => $service->id, 'name' => $service->name])->values(),
        ]])->header('Cache-Control', 'no-store');
    }

    public function show(StaffProfile $staff): JsonResponse
    {
        $staff->load([
            'user', 'services' => fn ($query) => $query->orderBy('name'),
            'weeklySchedules' => fn ($query) => $query->orderBy('day_of_week')->orderBy('start_time'),
            'scheduleExceptions' => fn ($query) => $query->where('exception_date', '>=', today())->orderBy('exception_date')->orderBy('start_time'),
        ])->loadCount(['services', 'appointments', 'weeklySchedules', 'scheduleExceptions']);

        return response()->json(['data' => $this->detail($staff)])->header('Cache-Control', 'no-store');
    }

    public function store(StaffRequest $request): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $data = $request->validated();
        $staff = DB::transaction(function () use ($data, $request): StaffProfile {
            $user = User::query()->create(['name' => $data['name'], 'email' => strtolower($data['email']), 'phone' => $data['phone'] ?? null, 'role' => User::ROLE_STAFF, 'is_active' => $request->boolean('is_active')]);
            $profile = $user->staffProfile()->create(['staff_type' => $data['staff_type'] ?? StaffProfile::TYPE_THERAPIST, 'position' => $data['position'] ?? null, 'specialization' => $data['specialization'] ?? null, 'bio' => $data['bio'] ?? null, 'hire_date' => $data['hire_date'] ?? null, 'is_bookable' => $request->boolean('is_bookable')]);
            $profile->services()->sync($data['service_ids'] ?? []);

            return $profile;
        });

        return response()->json(['data' => $this->freshDetail($staff), 'message' => 'Therapist account created.'], 201)->header('Cache-Control', 'no-store');
    }

    public function update(StaffRequest $request, StaffProfile $staff, StaffScheduleConflictGuard $guard): JsonResponse
    {
        $data = $request->validated();
        try {
            DB::transaction(function () use ($data, $guard, $request, $staff): void {
                $managed = StaffProfile::query()->lockForUpdate()->findOrFail($staff->id);
                $user = $managed->user()->lockForUpdate()->firstOrFail();
                $active = $request->boolean('is_active');
                $bookable = $request->boolean('is_bookable');
                $serviceIds = collect($data['service_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
                $removed = $managed->services()->pluck('services.id')->map(fn ($id) => (int) $id)->diff($serviceIds)->values()->all();
                if (($user->is_active && ! $active) || ($managed->is_bookable && ! $bookable)) {
                    $guard->assertCanMakeUnavailable($managed);
                } else {
                    $guard->assertCanRemoveServices($managed, $removed);
                }
                $user->update(['name' => $data['name'], 'phone' => $data['phone'] ?? null, 'is_active' => $active]);
                $managed->update(['staff_type' => $data['staff_type'] ?? $managed->staff_type, 'position' => $data['position'] ?? null, 'specialization' => $data['specialization'] ?? null, 'bio' => $data['bio'] ?? null, 'hire_date' => $data['hire_date'] ?? null, 'is_bookable' => $bookable]);
                $managed->services()->sync($serviceIds->all());
            });
        } catch (StaffScheduleConflictException $exception) {
            throw ValidationException::withMessages(['staff_eligibility' => $this->conflictMessage($exception)]);
        }

        return response()->json(['data' => $this->freshDetail($staff), 'message' => 'Therapist updated.'])->header('Cache-Control', 'no-store');
    }

    private function freshDetail(StaffProfile $staff): array
    {
        $staff->refresh()->load(['user', 'services', 'weeklySchedules', 'scheduleExceptions'])->loadCount(['services', 'appointments', 'weeklySchedules', 'scheduleExceptions']);

        return $this->detail($staff);
    }

    private function summary(StaffProfile $staff): array
    {
        return ['id' => $staff->id, 'name' => $staff->user?->name, 'email' => $staff->user?->email, 'phone' => $staff->user?->phone, 'is_active' => (bool) $staff->user?->is_active, 'staff_type' => $staff->staff_type, 'position' => $staff->position, 'specialization' => $staff->specialization, 'is_bookable' => (bool) $staff->is_bookable, 'services' => $staff->services->map(fn (Service $service) => ['id' => $service->id, 'name' => $service->name])->values(), 'services_count' => (int) ($staff->services_count ?? $staff->services->count()), 'appointments_count' => (int) ($staff->appointments_count ?? 0)];
    }

    private function detail(StaffProfile $staff): array
    {
        return [...$this->summary($staff), 'bio' => $staff->bio, 'hire_date' => $staff->hire_date?->toDateString(), 'weekly_schedules' => $staff->weeklySchedules->map(fn ($shift) => ['id' => $shift->id, 'day_of_week' => $shift->day_of_week, 'start_time' => substr((string) $shift->start_time, 0, 5), 'end_time' => substr((string) $shift->end_time, 0, 5), 'ends_next_day' => (bool) $shift->ends_next_day, 'is_available' => (bool) $shift->is_available])->values(), 'schedule_exceptions' => $staff->scheduleExceptions->map(fn ($exception) => ['id' => $exception->id, 'exception_date' => $exception->exception_date?->toDateString(), 'exception_type' => $exception->exception_type, 'start_time' => $exception->start_time ? substr((string) $exception->start_time, 0, 5) : null, 'end_time' => $exception->end_time ? substr((string) $exception->end_time, 0, 5) : null, 'ends_next_day' => (bool) $exception->ends_next_day, 'reason' => $exception->reason])->values()];
    }

    private function conflictMessage(StaffScheduleConflictException $exception): string
    {
        $number = $exception->conflicts[0]['appointment_number'] ?? null;

        return $number ? "Change blocked by future confirmed appointment {$number}." : 'Change blocked by a future confirmed appointment.';
    }

    private function pagination($paginator): array
    {
        return ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem()];
    }
}
