<?php

namespace App\Services;

use App\Models\Service;
use App\Models\StaffProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AppointmentAvailability
{
    public function __construct(private readonly AppointmentWorkflow $workflow)
    {
    }

    /**
     * @return array{
     *     month: string,
     *     service_id: int,
     *     preferred_staff_profile_id: int|null,
     *     dates: array<string, array<int, array{starts_at: string, time: string, label: string, staff_count: int}>>
     * }
     */
    public function month(Service $service, string $month, ?int $preferredStaffProfileId = null): array
    {
        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $staffProfiles = $this->eligibleStaff($service, $preferredStaffProfileId);
        $dates = [];

        if ($staffProfiles->isEmpty()) {
            return [
                'month' => $monthStart->format('Y-m'),
                'service_id' => $service->id,
                'preferred_staff_profile_id' => $preferredStaffProfileId,
                'dates' => [],
            ];
        }

        for ($day = $monthStart->copy(); $day->lte($monthEnd); $day->addDay()) {
            $daySlots = $this->slotsForDate($service, $staffProfiles, $day);

            if ($daySlots !== []) {
                $dates[$day->toDateString()] = $daySlots;
            }
        }

        return [
            'month' => $monthStart->format('Y-m'),
            'service_id' => $service->id,
            'preferred_staff_profile_id' => $preferredStaffProfileId,
            'dates' => $dates,
        ];
    }

    public function hasAvailableSlot(Service $service, Carbon $start, ?int $preferredStaffProfileId = null): bool
    {
        if ($start->lte(now())) {
            return false;
        }

        return $this->eligibleStaff($service, $preferredStaffProfileId)
            ->contains(fn (StaffProfile $staffProfile) => $this->workflow->isStaffAvailable($staffProfile, $service, $start));
    }

    /**
     * @return Collection<int, StaffProfile>
     */
    private function eligibleStaff(Service $service, ?int $preferredStaffProfileId = null): Collection
    {
        return StaffProfile::query()
            ->with('user')
            ->where('is_bookable', true)
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->whereHas('services', fn ($query) => $query->whereKey($service->id))
            ->when($preferredStaffProfileId, fn ($query) => $query->whereKey($preferredStaffProfileId))
            ->get();
    }

    /**
     * @param Collection<int, StaffProfile> $staffProfiles
     * @return array<int, array{starts_at: string, time: string, label: string, staff_count: int}>
     */
    private function slotsForDate(Service $service, Collection $staffProfiles, Carbon $day): array
    {
        $slots = [];
        $slot = $day->copy()->setTime(8, 0);
        $lastSlot = $day->copy()->setTime(20, 0);

        while ($slot->lte($lastSlot)) {
            if ($slot->gt(now())) {
                $availableStaffCount = $staffProfiles
                    ->filter(fn (StaffProfile $staffProfile) => $this->workflow->isStaffAvailable($staffProfile, $service, $slot))
                    ->count();

                if ($availableStaffCount > 0) {
                    $slots[] = [
                        'starts_at' => $slot->toDateTimeString(),
                        'time' => $slot->format('H:i'),
                        'label' => $slot->format('g:i A'),
                        'staff_count' => $availableStaffCount,
                    ];
                }
            }

            $slot->addMinutes(30);
        }

        return $slots;
    }
}
