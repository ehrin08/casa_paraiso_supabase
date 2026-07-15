<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Reception\CustomerUpdateRequest;
use App\Models\CustomerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileReceptionCustomerController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:255']]);
        $search = trim((string) ($data['q'] ?? ''));
        $customers = CustomerProfile::query()
            ->with('user')
            ->withCount(['appointments', 'transactions'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('customer_code', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($user) => $user
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"))))
            ->orderBy('customer_code')
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();

        return response()->json([
            'data' => $customers->getCollection()->map(fn (CustomerProfile $customer): array => $this->summary($customer))->values(),
            'meta' => $this->pagination($customers),
        ])->header('Cache-Control', 'no-store');
    }

    public function show(CustomerProfile $customer): JsonResponse
    {
        $customer->load([
            'user',
            'appointments' => fn ($query) => $query->with(['service', 'staffProfile.user'])->latest('scheduled_start_at')->limit(12),
            'transactions' => fn ($query) => $query->with(['service', 'appointment'])->latest()->limit(12),
            'feedback' => fn ($query) => $query->with(['service', 'appointment'])->latest('submitted_at')->limit(12),
        ])->loadCount(['appointments', 'transactions', 'feedback']);

        return response()->json(['data' => [
            ...$this->summary($customer),
            'email' => $customer->user?->email,
            'address' => $customer->address,
            'contact_preference' => $customer->contact_preference,
            'notes' => $customer->notes,
            'contact_preferences' => collect(CustomerProfile::CONTACT_PREFERENCES)
                ->map(fn (string $label, string $value): array => compact('value', 'label'))->values(),
            'appointments' => $customer->appointments->map(fn ($appointment): array => [
                'id' => $appointment->id,
                'appointment_number' => $appointment->appointment_number,
                'status' => $appointment->status,
                'starts_at' => $this->timestamp($appointment->scheduled_start_at ?? $appointment->requested_start_at),
                'service' => $appointment->service?->name,
                'therapist' => $appointment->staffProfile?->user?->name,
            ])->values(),
            'transactions' => $customer->transactions->map(fn ($transaction): array => [
                'id' => $transaction->id,
                'transaction_number' => $transaction->transaction_number,
                'amount' => number_format((float) $transaction->amount, 2, '.', ''),
                'payment_status' => $transaction->payment_status,
                'service' => $transaction->service?->name,
                'paid_at' => $this->timestamp($transaction->paid_at),
            ])->values(),
            'feedback' => $customer->feedback->map(fn ($feedback): array => [
                'id' => $feedback->id,
                'rating' => $feedback->rating,
                'comment' => $feedback->comment,
                'sentiment' => $feedback->sentiment_label,
                'service' => $feedback->service?->name,
                'submitted_at' => $this->timestamp($feedback->submitted_at),
            ])->values(),
        ]])->header('Cache-Control', 'no-store');
    }

    public function update(CustomerUpdateRequest $request, CustomerProfile $customer): JsonResponse
    {
        $data = $request->validated();
        $customer->user->update(['phone' => filled($data['phone'] ?? null) ? trim((string) $data['phone']) : null]);
        $customer->update([
            'address' => filled($data['address'] ?? null) ? trim((string) $data['address']) : null,
            'contact_preference' => $data['contact_preference'] ?? null,
            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
        ]);
        $customer->load('user')->loadCount(['appointments', 'transactions']);

        return response()->json([
            'data' => $this->summary($customer),
            'message' => 'Customer contact details updated.',
        ])->header('Cache-Control', 'no-store');
    }

    /** @return array<string, mixed> */
    private function summary(CustomerProfile $customer): array
    {
        return [
            'id' => $customer->id,
            'customer_code' => $customer->customer_code,
            'name' => $customer->user?->name,
            'phone' => $customer->user?->phone,
            'appointments_count' => (int) ($customer->appointments_count ?? 0),
            'transactions_count' => (int) ($customer->transactions_count ?? 0),
        ];
    }

    private function timestamp($value): ?string
    {
        return $value?->copy()->timezone(config('app.timezone'))->toIso8601String();
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
