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
        $today = Appointment::query()
            ->with($this->appointmentRelations())
            ->whereDate('scheduled_start_at', today())
            ->orderBy('scheduled_start_at')
            ->limit(12)
            ->get();

        return response()->json(['data' => [
            'summary' => [
                'today' => Appointment::query()->whereDate('scheduled_start_at', today())->count(),
                'upcoming' => Appointment::query()->where('status', Appointment::STATUS_CONFIRMED)->where('scheduled_start_at', '>=', now())->count(),
                'customers' => CustomerProfile::query()->count(),
                'payments_today' => number_format((float) Transaction::query()->whereDate('paid_at', today())->sum('amount'), 2, '.', ''),
            ],
            'today_appointments' => MobileOperationalAppointmentResource::collection($today)->resolve($request),
        ]])->header('Cache-Control', 'no-store');
    }

    /** @return array<int, string> */
    private function appointmentRelations(): array
    {
        return ['customerProfile.user', 'service', 'staffProfile.user', 'preferredStaffProfile.user', 'addons', 'transactions'];
    }
}
