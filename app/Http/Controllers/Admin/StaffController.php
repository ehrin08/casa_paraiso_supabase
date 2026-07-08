<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesIndexSorting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StaffRequest;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StaffController extends Controller
{
    use HandlesIndexSorting;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = (string) $request->query('status');
        $bookable = (string) $request->query('bookable');
        $sorts = [
            'name' => 'users.name',
            'position' => 'staff_profiles.position',
            'services' => 'services_count',
            'appointments' => 'appointments_count',
            'status' => 'users.is_active',
            'bookable' => 'staff_profiles.is_bookable',
        ];
        $sort = $this->indexSort($request, $sorts, 'status');
        $direction = $this->indexDirection($request, $sort === 'status' ? 'desc' : 'asc');

        $staffProfiles = StaffProfile::query()
            ->with(['user', 'services'])
            ->withCount(['services', 'appointments'])
            ->join('users', 'users.id', '=', 'staff_profiles.user_id')
            ->select('staff_profiles.*')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('users.phone', 'like', "%{$search}%")
                    ->orWhere('staff_profiles.position', 'like', "%{$search}%")
                    ->orWhere('staff_profiles.specialization', 'like', "%{$search}%");
            }))
            ->when($status === 'active', fn ($query) => $query->where('users.is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('users.is_active', false))
            ->when($bookable === 'yes', fn ($query) => $query->where('staff_profiles.is_bookable', true))
            ->when($bookable === 'no', fn ($query) => $query->where('staff_profiles.is_bookable', false))
            ->orderBy($sorts[$sort], $direction)
            ->orderBy('users.name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.staff.index', [
            'staffProfiles' => $staffProfiles,
            'search' => $search,
            'status' => $status,
            'bookable' => $bookable,
            'sort' => $sort,
            'direction' => $direction,
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
        $staff->load([
            'user',
            'services' => fn ($query) => $query->orderBy('name'),
            'weeklySchedules' => fn ($query) => $query->orderBy('day_of_week')->orderBy('start_time'),
            'scheduleExceptions' => fn ($query) => $query
                ->with('creator')
                ->where('exception_date', '>=', today())
                ->orderBy('exception_date')
                ->orderBy('start_time'),
        ])
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
