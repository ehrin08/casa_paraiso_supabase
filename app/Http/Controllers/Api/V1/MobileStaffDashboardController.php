<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\MobileOperationalAppointmentResource;
use App\Models\Appointment;
use App\Models\Feedback;
use App\Models\TherapistCommission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileStaffDashboardController
{
    public function __invoke(Request $request): JsonResponse
    {
        $staff = $request->user()->staffProfile;
        abort_unless($staff, 403);
        $appointments = Appointment::query()->where('staff_profile_id', $staff->id);
        $commissions = TherapistCommission::query()->where('staff_profile_id', $staff->id);
        $today = (clone $appointments)->with($this->relations())->whereDate('scheduled_start_at', today())
            ->orderBy('scheduled_start_at')->limit(12)->get();

        return response()->json(['data' => [
            'profile' => ['id' => $staff->id, 'name' => $request->user()->name, 'specialization' => $staff->specialization],
            'summary' => [
                'assigned_today' => (clone $appointments)->where('status', Appointment::STATUS_CONFIRMED)->whereDate('scheduled_start_at', today())->count(),
                'upcoming' => (clone $appointments)->where('status', Appointment::STATUS_CONFIRMED)->where('scheduled_start_at', '>', now())->count(),
                'completed_today' => (clone $appointments)->where('status', Appointment::STATUS_COMPLETED)->whereDate('completed_at', today())->count(),
                'feedback' => Feedback::query()->whereHas('appointment', fn ($query) => $query->where('staff_profile_id', $staff->id))->count(),
            ],
            'commissions' => [
                'pending' => $this->money((clone $commissions)->where('status', TherapistCommission::STATUS_PENDING)->sum('commission_amount')),
                'paid' => $this->money((clone $commissions)->where('status', TherapistCommission::STATUS_PAID)->sum('commission_amount')),
                'net' => $this->money((clone $commissions)->sum('commission_amount')),
            ],
            'today_appointments' => MobileOperationalAppointmentResource::collection($today)->resolve($request),
        ]])->header('Cache-Control', 'no-store');
    }

    private function relations(): array
    {
        return ['customerProfile.user', 'service', 'staffProfile.user', 'preferredStaffProfile.user', 'addons', 'transactions'];
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
