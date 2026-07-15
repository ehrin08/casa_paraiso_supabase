<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\CustomerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileStaffCustomerController
{
    public function index(Request $request): JsonResponse
    {
        $staffId = $this->staffId($request);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:255']]);
        $search = trim((string) ($data['q'] ?? ''));
        $customers = CustomerProfile::query()->with('user')
            ->withCount(['appointments as assigned_appointments_count' => fn ($query) => $query->where('staff_profile_id', $staffId)])
            ->whereHas('appointments', fn ($query) => $query->where('staff_profile_id', $staffId))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('customer_code', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))))
            ->orderBy('customer_code')->paginate((int) config('casa.pagination.per_page', 15))->withQueryString();

        return response()->json(['data' => $customers->getCollection()->map(fn ($customer) => $this->summary($customer))->values(), 'meta' => $this->pagination($customers)])
            ->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, CustomerProfile $customer): JsonResponse
    {
        $staffId = $this->staffId($request);
        abort_unless($customer->appointments()->where('staff_profile_id', $staffId)->exists(), 403);
        $customer->load('user');
        $appointments = $customer->appointments()->with(['service', 'transactions', 'feedback'])->where('staff_profile_id', $staffId)->latest('scheduled_start_at')->limit(15)->get();

        return response()->json(['data' => [
            ...$this->summary($customer, $appointments->count()),
            'email' => $customer->user?->email,
            'address' => $customer->address,
            'contact_preference' => $customer->contact_preference,
            'notes' => $customer->notes,
            'appointments' => $appointments->map(fn ($appointment) => [
                'id' => $appointment->id,
                'appointment_number' => $appointment->appointment_number,
                'status' => $appointment->status,
                'starts_at' => $this->timestamp($appointment->scheduled_start_at ?? $appointment->requested_start_at),
                'service' => $appointment->service?->name,
                'transaction' => $appointment->transactions->sortByDesc('id')->first() ? [
                    'amount' => $this->money($appointment->transactions->sortByDesc('id')->first()->amount),
                    'payment_status' => $appointment->transactions->sortByDesc('id')->first()->payment_status,
                ] : null,
                'feedback' => $appointment->feedback ? [
                    'rating' => $appointment->feedback->rating,
                    'comment' => $appointment->feedback->comment,
                    'sentiment' => $appointment->feedback->sentiment_label,
                ] : null,
            ])->values(),
        ]])->header('Cache-Control', 'no-store');
    }

    private function staffId(Request $request): int
    {
        abort_unless($request->user()->staffProfile, 403);

        return (int) $request->user()->staffProfile->id;
    }

    private function summary(CustomerProfile $customer, ?int $count = null): array
    {
        return ['id' => $customer->id, 'customer_code' => $customer->customer_code, 'name' => $customer->user?->name, 'phone' => $customer->user?->phone, 'assigned_appointments_count' => $count ?? (int) ($customer->assigned_appointments_count ?? 0)];
    }

    private function timestamp($value): ?string
    {
        return $value?->copy()->timezone(config('app.timezone'))->toIso8601String();
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function pagination($paginator): array
    {
        return ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem()];
    }
}
