<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\MobileStaffCommissionResource;
use App\Models\TherapistCommission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobileStaffCommissionController
{
    public function index(Request $request): JsonResponse
    {
        $staffId = $this->staffId($request);
        $data = $request->validate(['status' => ['nullable', Rule::in([TherapistCommission::STATUS_PENDING, TherapistCommission::STATUS_PAID])]]);
        $base = TherapistCommission::query()->where('staff_profile_id', $staffId);
        $commissions = (clone $base)->with($this->relations())
            ->when(! empty($data['status']), fn ($query) => $query->where('status', $data['status']))
            ->latest('earned_at')->paginate((int) config('casa.pagination.per_page', 15))->withQueryString();

        return response()->json([
            'data' => MobileStaffCommissionResource::collection($commissions->getCollection())->resolve($request),
            'summary' => [
                'pending' => $this->money((clone $base)->where('status', TherapistCommission::STATUS_PENDING)->sum('commission_amount')),
                'paid' => $this->money((clone $base)->where('status', TherapistCommission::STATUS_PAID)->sum('commission_amount')),
                'net' => $this->money((clone $base)->sum('commission_amount')),
            ],
            'meta' => $this->pagination($commissions),
        ])->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, TherapistCommission $commission): JsonResponse
    {
        abort_unless((int) $commission->staff_profile_id === $this->staffId($request), 403);
        $commission->load($this->relations());

        return response()->json(['data' => (new MobileStaffCommissionResource($commission))->resolve($request)])->header('Cache-Control', 'no-store');
    }

    private function staffId(Request $request): int
    {
        abort_unless($request->user()->staffProfile, 403);

        return (int) $request->user()->staffProfile->id;
    }

    private function relations(): array
    {
        return ['appointment.service', 'transaction'];
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
