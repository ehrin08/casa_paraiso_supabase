<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->with(['staffProfile', 'customerProfile'])->orderByRaw("FIELD(role, 'super_admin', 'admin', 'staff', 'customer')")->orderBy('name')->paginate(15),
            'assignableRoles' => [User::ROLE_ADMIN, User::ROLE_STAFF, User::ROLE_CUSTOMER],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data): void {
            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'role' => $data['role'],
                'is_active' => (bool) ($data['is_active'] ?? false),
            ]);
            $this->syncRoleProfile($user);
        });

        return back()->with('status', 'user-created');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isSuperAdmin(), 403, 'The protected super administrator cannot be changed.');
        $data = $this->validated($request, $user);

        DB::transaction(function () use ($data, $user): void {
            $user->update([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'role' => $data['role'],
                'is_active' => (bool) ($data['is_active'] ?? false),
            ]);
            $this->syncRoleProfile($user);
        });

        return back()->with('status', 'user-updated');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user?->id), Rule::notIn([strtolower(config('auth.super_admin_email'))])],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_STAFF, User::ROLE_CUSTOMER])],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function syncRoleProfile(User $user): void
    {
        if ($user->isStaff()) {
            $user->staffProfile()->withTrashed()->firstOrCreate([], ['is_bookable' => true])->restore();
        } elseif ($user->staffProfile()->withTrashed()->exists()) {
            $user->staffProfile()->withTrashed()->first()->delete();
        }

        if ($user->isCustomer()) {
            $profile = CustomerProfile::withTrashed()->firstOrNew(['user_id' => $user->id]);
            $profile->customer_code ??= $this->customerCode();
            $profile->save();
            $profile->restore();
        } elseif ($user->customerProfile()->withTrashed()->exists()) {
            $user->customerProfile()->withTrashed()->first()->delete();
        }
    }

    private function customerCode(): string
    {
        do {
            $code = 'CP-'.strtoupper(Str::random(8));
        } while (CustomerProfile::withTrashed()->where('customer_code', $code)->exists());

        return $code;
    }
}
