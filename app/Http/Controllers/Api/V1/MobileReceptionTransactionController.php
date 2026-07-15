<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\TransactionRequest;
use App\Http\Resources\Api\V1\MobileTransactionResource;
use App\Models\ApplicationSetting;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\Transaction;
use App\Services\TransactionWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobileReceptionTransactionController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payment_status' => ['nullable', Rule::in(Transaction::PAYMENT_STATUSES)],
            'q' => ['nullable', 'string', 'max:255'],
        ]);
        $search = trim((string) ($data['q'] ?? ''));
        $transactions = Transaction::query()
            ->with($this->relations())
            ->when(! empty($data['payment_status']), fn ($query) => $query->where('payment_status', $data['payment_status']))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('transaction_number', 'like', "%{$search}%")
                ->orWhereHas('customerProfile.user', fn ($user) => $user->where('name', 'like', "%{$search}%"))
                ->orWhereHas('service', fn ($service) => $service->where('name', 'like', "%{$search}%"))))
            ->latest()
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();

        return response()->json([
            'data' => MobileTransactionResource::collection($transactions->getCollection())->resolve($request),
            'summary' => [
                'paid' => number_format((float) Transaction::query()->where('payment_status', Transaction::PAYMENT_PAID)->sum('amount'), 2, '.', ''),
                'unpaid_count' => Transaction::query()->where('payment_status', Transaction::PAYMENT_UNPAID)->count(),
                'partial_count' => Transaction::query()->where('payment_status', Transaction::PAYMENT_PARTIAL)->count(),
            ],
            'meta' => $this->pagination($transactions),
        ])->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        $transaction->load($this->relations());

        return response()->json(['data' => (new MobileTransactionResource($transaction))->resolve($request)])
            ->header('Cache-Control', 'no-store');
    }

    public function options(): JsonResponse
    {
        return response()->json(['data' => [
            'customers' => CustomerProfile::query()->with('user')->orderBy('customer_code')->get()->map(fn (CustomerProfile $customer): array => [
                'id' => $customer->id, 'customer_code' => $customer->customer_code, 'name' => $customer->user?->name,
            ])->values(),
            'services' => Service::query()->orderBy('name')->get()->map(fn (Service $service): array => [
                'id' => $service->id, 'name' => $service->name,
            ])->values(),
            'appointments' => Appointment::query()->with(['customerProfile.user', 'service', 'addons'])
                ->whereIn('status', [Appointment::STATUS_CONFIRMED, Appointment::STATUS_COMPLETED])
                ->latest('scheduled_start_at')->get()->map(fn (Appointment $appointment): array => [
                    'id' => $appointment->id,
                    'appointment_number' => $appointment->appointment_number,
                    'customer_profile_id' => $appointment->customer_profile_id,
                    'service_id' => $appointment->service_id,
                    'customer_name' => $appointment->customerProfile?->user?->name,
                    'service_name' => $appointment->service?->name,
                    'expected_amount' => number_format((float) $appointment->expectedAmount(), 2, '.', ''),
                ])->values(),
            'payment_statuses' => Transaction::PAYMENT_STATUSES,
            'payment_methods' => Transaction::PAYMENT_METHODS,
            'default_payment_method' => ApplicationSetting::current()->default_payment_method,
        ]])->header('Cache-Control', 'no-store');
    }

    public function store(TransactionRequest $request, TransactionWorkflow $workflow): JsonResponse
    {
        $transaction = $workflow->persist(new Transaction, $request->validated(), $request->user()->id);
        $transaction->load($this->relations());

        return response()->json([
            'data' => (new MobileTransactionResource($transaction))->resolve($request),
            'message' => 'Payment record created.',
        ], 201)->header('Cache-Control', 'no-store');
    }

    public function update(TransactionRequest $request, Transaction $transaction, TransactionWorkflow $workflow): JsonResponse
    {
        $transaction = $workflow->persist($transaction, $request->validated(), $request->user()->id);
        $transaction->load($this->relations());

        return response()->json([
            'data' => (new MobileTransactionResource($transaction))->resolve($request),
            'message' => 'Payment record updated.',
        ])->header('Cache-Control', 'no-store');
    }

    /** @return array<int, string> */
    private function relations(): array
    {
        return ['customerProfile.user', 'service', 'appointment', 'recorder'];
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
