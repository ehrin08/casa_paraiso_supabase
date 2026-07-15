<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Admin\CommissionPayoutRequest;
use App\Http\Resources\Api\V1\MobileStaffCommissionResource;
use App\Models\StaffProfile;
use App\Models\TherapistCommission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MobileAdminCommissionController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['staff_profile_id' => ['nullable', 'integer', Rule::exists('staff_profiles', 'id')], 'status' => ['nullable', Rule::in(TherapistCommission::STATUSES)], 'date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from']]);
        $base = TherapistCommission::query();
        $commissions = (clone $base)->with(['staffProfile.user', 'appointment.service', 'transaction', 'paidBy'])
            ->when(! empty($data['staff_profile_id']), fn ($query) => $query->where('staff_profile_id', $data['staff_profile_id']))
            ->when(! empty($data['status']), fn ($query) => $query->where('status', $data['status']))
            ->when(! empty($data['date_from']), fn ($query) => $query->whereDate('earned_at', '>=', $data['date_from']))
            ->when(! empty($data['date_to']), fn ($query) => $query->whereDate('earned_at', '<=', $data['date_to']))
            ->latest('earned_at')->paginate((int) config('casa.pagination.per_page', 15))->withQueryString();

        return response()->json([
            'data' => MobileStaffCommissionResource::collection($commissions->getCollection())->resolve($request),
            'summary' => ['pending' => $this->money((clone $base)->where('status', TherapistCommission::STATUS_PENDING)->sum('commission_amount')), 'paid' => $this->money((clone $base)->where('status', TherapistCommission::STATUS_PAID)->sum('commission_amount')), 'net' => $this->money((clone $base)->sum('commission_amount'))],
            'staff' => StaffProfile::query()->with('user')->get()->sortBy('user.name')->map(fn (StaffProfile $staff) => ['id' => $staff->id, 'name' => $staff->user?->name])->values(),
            'meta' => $this->pagination($commissions),
        ])->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, TherapistCommission $commission): JsonResponse
    {
        $commission->load(['staffProfile.user', 'appointment.service', 'transaction', 'adjustedCommission', 'adjustments', 'paidBy']);

        return response()->json(['data' => (new MobileStaffCommissionResource($commission))->resolve($request)])->header('Cache-Control', 'no-store');
    }

    public function pay(CommissionPayoutRequest $request, TherapistCommission $commission): JsonResponse
    {
        DB::transaction(function () use ($commission, $request): void {
            $locked = TherapistCommission::query()->lockForUpdate()->findOrFail($commission->id);
            if ($locked->status !== TherapistCommission::STATUS_PENDING) {
                throw ValidationException::withMessages(['status' => 'Paid commissions cannot be changed.']);
            }
            if ((float) $locked->commission_amount === 0.0) {
                throw ValidationException::withMessages(['status' => 'A zero commission does not require payout recording.']);
            }
            $locked->update(['status' => TherapistCommission::STATUS_PAID, 'paid_at' => Carbon::parse($request->validated('paid_at'))->endOfDay(), 'paid_by' => $request->user()->id, 'notes' => filled($request->validated('notes')) ? trim($request->validated('notes')) : null]);
        }, 3);
        $commission->refresh()->load(['staffProfile.user', 'appointment.service', 'transaction', 'paidBy']);

        return response()->json(['data' => (new MobileStaffCommissionResource($commission))->resolve($request), 'message' => 'Commission payout recorded.'])->header('Cache-Control', 'no-store');
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
