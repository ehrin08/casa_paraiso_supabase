<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StaffRequest;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        $staffProfiles = StaffProfile::query()
            ->with(['user', 'services'])
            ->withCount(['services', 'appointments'])
            ->join('users', 'users.id', '=', 'staff_profiles.user_id')
            ->select('staff_profiles.*')
            ->orderByDesc('users.is_active')
            ->orderBy('users.name')
            ->paginate(10);

        return view('admin.staff.index', [
            'staffProfiles' => $staffProfiles,
            'activeAccountCount' => User::query()->where('role', User::ROLE_STAFF)->where('is_active', true)->count(),
            'inactiveAccountCount' => User::query()->where('role', User::ROLE_STAFF)->where('is_active', false)->count(),
            'bookableCount' => StaffProfile::query()->where('is_bookable', true)->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.staff.create', [
            'staffProfile' => new StaffProfile(['is_bookable' => true]),
            'staffUser' => new User(['is_active' => true]),
            'services' => $this->activeServices(),
            'assignedServiceIds' => [],
        ]);
    }

    public function store(StaffRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $staffProfile = DB::transaction(function () use ($data, $request): StaffProfile {
            $staffUser = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'role' => User::ROLE_STAFF,
                'is_active' => $request->boolean('is_active'),
            ]);

            $staffProfile = $staffUser->staffProfile()->create([
                'position' => $data['position'] ?? null,
                'specialization' => $data['specialization'] ?? null,
                'bio' => $data['bio'] ?? null,
                'hire_date' => $data['hire_date'] ?? null,
                'is_bookable' => $request->boolean('is_bookable'),
            ]);

            $staffProfile->services()->sync($data['service_ids'] ?? []);

            return $staffProfile;
        });

        return redirect()
            ->route('admin.staff.show', $staffProfile)
            ->with('status', 'staff-created');
    }

    public function show(StaffProfile $staff): View
    {
        $staff->load(['user', 'services' => fn ($query) => $query->orderBy('name')])
            ->loadCount(['services', 'appointments', 'weeklySchedules', 'scheduleExceptions']);

        return view('admin.staff.show', [
            'staffProfile' => $staff,
        ]);
    }

    public function edit(StaffProfile $staff): View
    {
        $staff->load(['user', 'services']);

        return view('admin.staff.edit', [
            'staffProfile' => $staff,
            'staffUser' => $staff->user,
            'services' => $this->activeServices(),
            'assignedServiceIds' => $staff->services->pluck('id')->all(),
        ]);
    }

    public function update(StaffRequest $request, StaffProfile $staff): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request, $staff): void {
            $userData = [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'is_active' => $request->boolean('is_active'),
            ];

            if (! empty($data['password'])) {
                $userData['password'] = $data['password'];
            }

            $staff->user->update($userData);

            $staff->update([
                'position' => $data['position'] ?? null,
                'specialization' => $data['specialization'] ?? null,
                'bio' => $data['bio'] ?? null,
                'hire_date' => $data['hire_date'] ?? null,
                'is_bookable' => $request->boolean('is_bookable'),
            ]);

            $staff->services()->sync($data['service_ids'] ?? []);
        });

        return redirect()
            ->route('admin.staff.show', $staff)
            ->with('status', 'staff-updated');
    }

    private function activeServices()
    {
        return Service::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
