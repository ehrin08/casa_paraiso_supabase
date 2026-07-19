<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\MobileOperationalAppointmentResource;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileReceptionDashboardController
{
    public function __invoke(Request $request): JsonResponse
    {
        $startOfToday = now()->startOfDay();
        $startOfTomorrow = $startOfToday->copy()->addDay();
        $today = Appointment::query()
            ->with($this->appointmentRelations())
            ->where('scheduled_start_at', '>=', $startOfToday)
            ->where('scheduled_start_at', '<', $startOfTomorrow)
            ->orderBy('scheduled_start_at')
            ->limit(12)
            ->get();
        $appointmentSummary = Appointment::query()->selectRaw(
            'COUNT(CASE WHEN scheduled_start_at >= ? AND scheduled_start_at < ? THEN 1 END) AS today_count, COUNT(CASE WHEN status = ? AND scheduled_start_at >= ? THEN 1 END) AS upcoming_count',
            [$startOfToday, $startOfTomorrow, Appointment::STATUS_CONFIRMED, now()],
        )->first();

        return response()->json(['data' => [
            'summary' => [
                'today' => (int) ($appointmentSummary->today_count ?? 0),
                'upcoming' => (int) ($appointmentSummary->upcoming_count ?? 0),
                'customers' => CustomerProfile::query()->count(),
                'payments_today' => number_format((float) Transaction::query()->where('paid_at', '>=', $startOfToday)->where('paid_at', '<', $startOfTomorrow)->sum('amount'), 2, '.', ''),
            ],
            'today_appointments' => MobileOperationalAppointmentResource::collection($today)->resolve($request),
        ]])->header('Cache-Control', 'no-store');
    }

    /** @return array<int, string> */
    private function appointmentRelations(): array
    {
        return ['customerProfile.user', 'service', 'staffProfile.user', 'preferredStaffProfile.user', 'addons', 'latestTransaction'];
    }
}
