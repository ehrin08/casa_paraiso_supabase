<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Concerns\HandlesIndexSorting;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppointmentRequest;
use App\Models\Appointment;
use App\Services\AppointmentWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    use HandlesIndexSorting;

    public function index(Request $request): View
    {
        $staffProfile = $request->user()->staffProfile;
        $serviceIds = $staffProfile?->services()->pluck('services.id') ?? collect();
        $status = (string) $request->query('status');
        $search = trim((string) $request->query('q'));
        $sorts = [
            'number' => 'appointments.appointment_number',
            'customer' => 'appointment_customers.name',
            'service' => 'appointment_services.name',
            'schedule' => 'appointments.requested_start_at',
            'status' => 'appointments.status',
        ];
        $sort = $this->indexSort($request, $sorts, 'schedule');
        $direction = $this->indexDirection($request, 'desc');

        $appointments = Appointment::query()
            ->with(['customerProfile.user', 'service', 'staffProfile.user'])
            ->leftJoin('customer_profiles as appointment_customer_profiles', 'appointment_customer_profiles.id', '=', 'appointments.customer_profile_id')
            ->leftJoin('users as appointment_customers', 'appointment_customers.id', '=', 'appointment_customer_profiles.user_id')
            ->leftJoin('services as appointment_services', 'appointment_services.id', '=', 'appointments.service_id')
            ->select('appointments.*')
            ->where(function ($query) use ($staffProfile, $serviceIds): void {
                $query->where('staff_profile_id', $staffProfile?->id ?? 0)
                    ->orWhere(function ($query) use ($serviceIds): void {
                        $query->where('appointments.status', Appointment::STATUS_PENDING)
                            ->whereIn('appointments.service_id', $serviceIds);
                    });
            })
            ->when(in_array($status, Appointment::STATUSES, true), fn ($query) => $query->where('appointments.status', $status))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('appointments.appointment_number', 'like', "%{$search}%")
                    ->orWhere('appointment_customers.name', 'like', "%{$search}%")
                    ->orWhere('appointment_services.name', 'like', "%{$search}%");
            }))
            ->orderBy($sorts[$sort], $direction)
            ->orderByDesc('appointments.requested_start_at')
            ->paginate(12)
            ->withQueryString();

        return view('staff.appointments.index', [
            'appointments' => $appointments,
            'staffProfile' => $staffProfile,
            'status' => $status,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function show(Request $request, Appointment $appointment): View
    {
        $this->authorizeOperationalAccess($request, $appointment);

        $appointment->load(['customerProfile.user', 'service', 'staffProfile.user', 'transactions', 'feedback']);

        return view('staff.appointments.show', [
            'appointment' => $appointment,
        ]);
    }

    public function update(AppointmentRequest $request, Appointment $appointment, AppointmentWorkflow $workflow): RedirectResponse
    {
        $this->authorizeOperationalAccess($request, $appointment);

        $data = $request->validated();
        $staffProfile = $request->user()->staffProfile;
        $status = $data['status'] ?? $appointment->status;

        if ($status === Appointment::STATUS_CONFIRMED) {
            $scheduledStart = ! empty($data['scheduled_start_at'])
                ? Carbon::parse($data['scheduled_start_at'])
                : Carbon::parse($appointment->requested_start_at);
            $scheduledEnd = $workflow->scheduledEnd($scheduledStart, $appointment->service);

            if (! $staffProfile || ! $workflow->isStaffAvailable($staffProfile, $appointment->service, $scheduledStart, $scheduledEnd, $appointment)) {
                return back()->withErrors(['scheduled_start_at' => __('Your schedule cannot accept this appointment time.')]);
            }

            $appointment->update([
                'staff_profile_id' => $staffProfile->id,
                'scheduled_start_at' => $scheduledStart,
                'scheduled_end_at' => $scheduledEnd,
                'internal_notes' => $data['internal_notes'] ?? $appointment->internal_notes,
                'updated_by' => $request->user()->id,
            ]);
        }

        if (in_array($status, [Appointment::STATUS_COMPLETED, Appointment::STATUS_NO_SHOW, Appointment::STATUS_CANCELLED], true)
            && (int) $appointment->staff_profile_id !== (int) $staffProfile?->id) {
            abort(403);
        }

        $workflow->changeStatus($appointment, $status, $request->user()->id, $data['reason'] ?? null);

        return redirect()
            ->route('staff.appointments.show', $appointment)
            ->with('status', 'appointment-updated');
    }

    private function authorizeOperationalAccess(Request $request, Appointment $appointment): void
    {
        $staffProfile = $request->user()->staffProfile;
        $serviceIds = $staffProfile?->services()->pluck('services.id') ?? collect();

        $allowed = (int) $appointment->staff_profile_id === (int) ($staffProfile?->id ?? 0)
            || ($appointment->status === Appointment::STATUS_PENDING && $serviceIds->contains($appointment->service_id));

        abort_unless($allowed, 403);
    }
}
