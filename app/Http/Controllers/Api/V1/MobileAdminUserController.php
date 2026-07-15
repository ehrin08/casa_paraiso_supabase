<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\StaffScheduleConflictException;
use App\Models\CustomerProfile;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\StaffScheduleConflictGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MobileAdminUserController
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $users = User::query()->with(['staffProfile', 'customerProfile'])
            ->orderByRaw("CASE role WHEN 'super_admin' THEN 1 WHEN 'admin' THEN 2 WHEN 'receptionist' THEN 3 WHEN 'staff' THEN 4 WHEN 'customer' THEN 5 ELSE 6 END")
            ->orderBy('name')->paginate((int) config('casa.pagination.per_page', 15))->withQueryString();

        return response()->json(['data' => $users->getCollection()->map(fn (User $user) => $this->serialize($user))->values(), 'roles' => [User::ROLE_ADMIN, User::ROLE_RECEPTIONIST, User::ROLE_STAFF, User::ROLE_CUSTOMER], 'meta' => $this->pagination($users)])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $data = $this->validated($request);
        $user = DB::transaction(function () use ($data): User {
            $user = User::query()->create(['name' => $data['name'], 'email' => strtolower($data['email']), 'role' => $data['role'], 'is_active' => (bool) ($data['is_active'] ?? false)]);
            $this->syncRoleProfile($user);

            return $user;
        });

        return response()->json(['data' => $this->serialize($user->load(['staffProfile', 'customerProfile'])), 'message' => 'User access created.'], 201)->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, User $user, StaffScheduleConflictGuard $guard): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        abort_if($user->isSuperAdmin(), 403, 'The protected super administrator cannot be changed.');
        $data = $this->validated($request, $user);
        try {
            DB::transaction(function () use ($data, $guard, $user): void {
                $staff = StaffProfile::withTrashed()->where('user_id', $user->id)->lockForUpdate()->first();
                $managed = User::query()->lockForUpdate()->findOrFail($user->id);
                $active = (bool) ($data['is_active'] ?? false);
                if ($staff && (($managed->isStaff() && $data['role'] !== User::ROLE_STAFF) || ($managed->is_active && ! $active))) {
                    $guard->assertCanMakeUnavailable($staff);
                }
                $managed->update(['name' => $data['name'], 'email' => strtolower($data['email']), 'role' => $data['role'], 'is_active' => $active]);
                $this->syncRoleProfile($managed);
            });
        } catch (StaffScheduleConflictException $exception) {
            $number = $exception->conflicts[0]['appointment_number'] ?? null;
            throw ValidationException::withMessages(['staff_eligibility' => $number ? "Change blocked by future confirmed appointment {$number}." : 'Change blocked by a future confirmed appointment.']);
        }
        $user->refresh()->load(['staffProfile', 'customerProfile']);

        return response()->json(['data' => $this->serialize($user), 'message' => 'User access updated.'])->header('Cache-Control', 'no-store');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user?->id), Rule::notIn([strtolower(config('auth.super_admin_email'))]), function (string $attribute, mixed $value, \Closure $fail) use ($user): void {
                if ($user?->google_id && strcasecmp(trim((string) $value), $user->email) !== 0) {
                    $fail('A Google-linked email is managed by Google and cannot be changed here.');
                }
            }],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_RECEPTIONIST, User::ROLE_STAFF, User::ROLE_CUSTOMER])],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function syncRoleProfile(User $user): void
    {
        if ($user->isStaff()) {
            $profile = $user->staffProfile()->firstOrCreate([], ['is_bookable' => true]);
            if ($profile->trashed()) {
                $profile->restore();
            }
        } elseif ($user->staffProfile()->exists()) {
            $user->staffProfile()->first()->delete();
        }
        if ($user->isCustomer()) {
            CustomerProfile::provisionFor($user);
        } elseif ($user->customerProfile()->exists()) {
            $user->customerProfile()->first()->delete();
        }
    }

    private function serialize(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role, 'is_active' => (bool) $user->is_active, 'google_linked' => filled($user->google_id), 'email_verified' => $user->email_verified_at !== null, 'protected' => $user->isSuperAdmin(), 'staff_profile_id' => $user->staffProfile?->id, 'customer_profile_id' => $user->customerProfile?->id];
    }

    private function pagination($paginator): array
    {
        return ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem()];
    }
}
