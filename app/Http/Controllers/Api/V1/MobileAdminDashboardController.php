<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\MobileOperationalAppointmentResource;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Feedback;
use App\Models\PromotionSuggestion;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileAdminDashboardController
{
    public function __invoke(Request $request): JsonResponse
    {
        $today = today();
        $upcoming = Appointment::query()
            ->with(['customerProfile.user', 'service', 'staffProfile.user', 'preferredStaffProfile.user', 'addons', 'transactions'])
            ->where('status', Appointment::STATUS_CONFIRMED)
            ->where('scheduled_start_at', '>=', now())
            ->orderBy('scheduled_start_at')
            ->limit(8)
            ->get();
        $todayAppointments = Appointment::query()
            ->with(['customerProfile.user', 'service', 'staffProfile.user', 'preferredStaffProfile.user', 'addons', 'transactions'])
            ->whereDate('scheduled_start_at', $today)
            ->orderBy('scheduled_start_at')
            ->limit(12)
            ->get();
        $todayCount = Appointment::query()->whereDate('scheduled_start_at', $today)->count();
        $upcomingCount = Appointment::query()->where('status', Appointment::STATUS_CONFIRMED)->where('scheduled_start_at', '>=', now())->count();
        $todayRevenue = $this->money(Transaction::query()->where('payment_status', Transaction::PAYMENT_PAID)->whereDate('paid_at', $today)->sum('amount'));
        $customerCount = CustomerProfile::query()->count();

        return response()->json(['data' => [
            'summary' => [
                'today' => $todayCount,
                'upcoming' => $upcomingCount,
                'payments_today' => $todayRevenue,
                'today_appointments' => $todayCount,
                'upcoming_appointments' => $upcomingCount,
                'today_revenue' => $todayRevenue,
                'new_feedback' => Feedback::query()->whereDate('submitted_at', $today)->count(),
                'available_rewards' => PromotionSuggestion::query()->where('status', PromotionSuggestion::STATUS_SUGGESTED)->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(),
                'customers' => $customerCount,
                'active_services' => Service::query()->where('is_active', true)->count(),
                'bookable_therapists' => StaffProfile::query()->where('is_bookable', true)->whereHas('user', fn ($query) => $query->where('is_active', true))->count(),
            ],
            'today_appointments' => MobileOperationalAppointmentResource::collection($todayAppointments)->resolve($request),
            'upcoming_appointments' => MobileOperationalAppointmentResource::collection($upcoming)->resolve($request),
            'is_super_admin' => $request->user()->isSuperAdmin(),
        ]])->header('Cache-Control', 'no-store');
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
