<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\MobileTransactionResource;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobileStaffTransactionController
{
    public function index(Request $request): JsonResponse
    {
        $staffId = $this->staffId($request);
        $data = $request->validate(['payment_status' => ['nullable', Rule::in(Transaction::PAYMENT_STATUSES)], 'q' => ['nullable', 'string', 'max:255']]);
        $search = trim((string) ($data['q'] ?? ''));
        $base = Transaction::query()->whereHas('appointment', fn ($query) => $query->where('staff_profile_id', $staffId));
        $transactions = (clone $base)->with($this->relations())
            ->when(! empty($data['payment_status']), fn ($query) => $query->where('payment_status', $data['payment_status']))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('transaction_number', 'like', "%{$search}%")
                ->orWhereHas('customerProfile.user', fn ($user) => $user->where('name', 'like', "%{$search}%"))))
            ->latest()->paginate((int) config('casa.pagination.per_page', 15))->withQueryString();

        return response()->json([
            'data' => MobileTransactionResource::collection($transactions->getCollection())->resolve($request),
            'summary' => [
                'paid' => $this->money((clone $base)->where('payment_status', Transaction::PAYMENT_PAID)->sum('amount')),
                'unpaid_count' => (clone $base)->where('payment_status', Transaction::PAYMENT_UNPAID)->count(),
                'partial_count' => (clone $base)->where('payment_status', Transaction::PAYMENT_PARTIAL)->count(),
            ],
            'meta' => $this->pagination($transactions),
        ])->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        $staffId = $this->staffId($request);
        abort_unless((int) $transaction->appointment?->staff_profile_id === $staffId, 403);
        $transaction->load($this->relations());

        return response()->json(['data' => (new MobileTransactionResource($transaction))->resolve($request)])->header('Cache-Control', 'no-store');
    }

    private function staffId(Request $request): int
    {
        abort_unless($request->user()->staffProfile, 403);

        return (int) $request->user()->staffProfile->id;
    }

    private function relations(): array
    {
        return ['customerProfile.user', 'service', 'appointment', 'recorder'];
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
