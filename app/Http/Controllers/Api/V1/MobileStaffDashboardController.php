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
        $startOfToday = now()->startOfDay();
        $startOfTomorrow = $startOfToday->copy()->addDay();
        $today = (clone $appointments)->with($this->relations())->where('scheduled_start_at', '>=', $startOfToday)->where('scheduled_start_at', '<', $startOfTomorrow)
            ->orderBy('scheduled_start_at')->limit(12)->get();
        $appointmentSummary = (clone $appointments)->selectRaw(
            'COUNT(CASE WHEN status = ? AND scheduled_start_at >= ? AND scheduled_start_at < ? THEN 1 END) AS assigned_today, COUNT(CASE WHEN status = ? AND scheduled_start_at > ? THEN 1 END) AS upcoming_count, COUNT(CASE WHEN status = ? AND completed_at >= ? AND completed_at < ? THEN 1 END) AS completed_today',
            [Appointment::STATUS_CONFIRMED, $startOfToday, $startOfTomorrow, Appointment::STATUS_CONFIRMED, now(), Appointment::STATUS_COMPLETED, $startOfToday, $startOfTomorrow],
        )->first();

        return response()->json(['data' => [
            'profile' => ['id' => $staff->id, 'name' => $request->user()->name, 'specialization' => $staff->specialization],
            'summary' => [
                'assigned_today' => (int) ($appointmentSummary->assigned_today ?? 0),
                'upcoming' => (int) ($appointmentSummary->upcoming_count ?? 0),
                'completed_today' => (int) ($appointmentSummary->completed_today ?? 0),
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
        return ['customerProfile.user', 'service', 'staffProfile.user', 'preferredStaffProfile.user', 'addons', 'latestTransaction'];
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
