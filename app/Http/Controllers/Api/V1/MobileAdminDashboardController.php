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
        $today = now()->startOfDay();
        $tomorrow = $today->copy()->addDay();
        $upcoming = Appointment::query()
            ->with(['customerProfile.user', 'service', 'staffProfile.user', 'preferredStaffProfile.user', 'addons', 'latestTransaction'])
            ->where('status', Appointment::STATUS_CONFIRMED)
            ->where('scheduled_start_at', '>=', now())
            ->orderBy('scheduled_start_at')
            ->limit(8)
            ->get();
        $todayAppointments = Appointment::query()
            ->with(['customerProfile.user', 'service', 'staffProfile.user', 'preferredStaffProfile.user', 'addons', 'latestTransaction'])
            ->where('scheduled_start_at', '>=', $today)
            ->where('scheduled_start_at', '<', $tomorrow)
            ->orderBy('scheduled_start_at')
            ->limit(12)
            ->get();
        $appointmentSummary = Appointment::query()->selectRaw(
            'COUNT(CASE WHEN scheduled_start_at >= ? AND scheduled_start_at < ? THEN 1 END) AS today_count, COUNT(CASE WHEN status = ? AND scheduled_start_at >= ? THEN 1 END) AS upcoming_count',
            [$today, $tomorrow, Appointment::STATUS_CONFIRMED, now()],
        )->first();
        $todayCount = (int) ($appointmentSummary->today_count ?? 0);
        $upcomingCount = (int) ($appointmentSummary->upcoming_count ?? 0);
        $todayRevenue = $this->money(Transaction::query()->where('payment_status', Transaction::PAYMENT_PAID)->where('paid_at', '>=', $today)->where('paid_at', '<', $tomorrow)->sum('amount'));
        $customerCount = CustomerProfile::query()->count();

        return response()->json(['data' => [
            'summary' => [
                'today' => $todayCount,
                'upcoming' => $upcomingCount,
                'payments_today' => $todayRevenue,
                'today_appointments' => $todayCount,
                'upcoming_appointments' => $upcomingCount,
                'today_revenue' => $todayRevenue,
                'new_feedback' => Feedback::query()->where('submitted_at', '>=', $today)->where('submitted_at', '<', $tomorrow)->count(),
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
