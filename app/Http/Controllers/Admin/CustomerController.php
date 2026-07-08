<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CustomerNoteRequest;
use App\Models\CustomerProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $customers = CustomerProfile::query()
            ->with('user')
            ->withCount(['appointments', 'transactions', 'feedback', 'promotionSuggestions'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('customer_code', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($userQuery) => $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"));
                });
            })
            ->join('users', 'users.id', '=', 'customer_profiles.user_id')
            ->select('customer_profiles.*')
            ->orderBy('users.name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.customers.index', [
            'customers' => $customers,
            'search' => $search,
            'totalCustomers' => CustomerProfile::query()->count(),
        ]);
    }

    public function show(CustomerProfile $customer): View
    {
        $customer->load([
            'user',
            'appointments' => fn ($query) => $query
                ->with(['service', 'staffProfile.user'])
                ->latest('requested_start_at')
                ->limit(8),
            'transactions' => fn ($query) => $query
                ->with(['service', 'appointment'])
                ->latest()
                ->limit(8),
            'feedback' => fn ($query) => $query
                ->with(['service', 'appointment'])
                ->latest('submitted_at')
                ->limit(8),
            'promotionSuggestions' => fn ($query) => $query
                ->with(['rfmSegment', 'promotionRule'])
                ->latest()
                ->limit(8),
        ])->loadCount(['appointments', 'transactions', 'feedback', 'promotionSuggestions']);

        return view('admin.customers.show', [
            'customer' => $customer,
        ]);
    }

    public function update(CustomerNoteRequest $request, CustomerProfile $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('status', 'customer-updated');
    }
}
