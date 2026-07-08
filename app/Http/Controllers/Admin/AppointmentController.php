<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesIndexSorting;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppointmentRequest;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Transaction;
use App\Services\AppointmentWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    use HandlesIndexSorting;

    public function index(Request $request): View
    {
        $status = (string) $request->query('status');
        $search = trim((string) $request->query('q'));
        $sorts = [
            'number' => 'appointments.appointment_number',
            'customer' => 'appointment_customers.name',
            'service' => 'appointment_services.name',
            'schedule' => 'appointments.requested_start_at',
            'staff' => 'appointment_staff_users.name',
            'status' => 'appointments.status',
        ];
        $sort = $this->indexSort($request, $sorts, 'schedule');
        $direction = $this->indexDirection($request, 'desc');

        $appointments = Appointment::query()
            ->with(['customerProfile.user', 'service', 'staffProfile.user'])
            ->leftJoin('customer_profiles as appointment_customer_profiles', 'appointment_customer_profiles.id', '=', 'appointments.customer_profile_id')
            ->leftJoin('users as appointment_customers', 'appointment_customers.id', '=', 'appointment_customer_profiles.user_id')
            ->leftJoin('services as appointment_services', 'appointment_services.id', '=', 'appointments.service_id')
            ->leftJoin('staff_profiles as appointment_staff_profiles', 'appointment_staff_profiles.id', '=', 'appointments.staff_profile_id')
            ->leftJoin('users as appointment_staff_users', 'appointment_staff_users.id', '=', 'appointment_staff_profiles.user_id')
            ->select('appointments.*')
            ->when(in_array($status, Appointment::STATUSES, true), fn ($query) => $query->where('appointments.status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('appointments.appointment_number', 'like', "%{$search}%")
                        ->orWhere('appointment_customers.name', 'like', "%{$search}%")
                        ->orWhere('appointment_services.name', 'like', "%{$search}%")
                        ->orWhere('appointment_staff_users.name', 'like', "%{$search}%");
                });
            })
            ->orderBy($sorts[$sort], $direction)
            ->orderByDesc('appointments.requested_start_at')
            ->paginate(12)
            ->withQueryString();

        $formData = $this->formData(new Appointment([
            'requested_start_at' => now()->addDay()->setTime(10, 0),
            'status' => Appointment::STATUS_PENDING,
        ]));

        return view('admin.appointments.index', [
            'appointments' => $appointments,
            'status' => $status,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
            'summary' => [
                'pending' => Appointment::query()->where('status', Appointment::STATUS_PENDING)->count(),
                'confirmed' => Appointment::query()->where('status', Appointment::STATUS_CONFIRMED)->count(),
                'completed' => Appointment::query()->where('status', Appointment::STATUS_COMPLETED)->count(),
            ],
            ...$formData,
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.appointments.create', $this->formData(new Appointment([
            'requested_start_at' => now()->addDay()->setTime(10, 0),
            'status' => Appointment::STATUS_PENDING,
            'customer_profile_id' => $request->integer('customer_profile_id') ?: null,
        ])));
    }

    public function store(AppointmentRequest $request, AppointmentWorkflow $workflow): RedirectResponse
    {
        $appointment = $this->persistAppointment(new Appointment, $request, $workflow);

        return redirect()
            ->route('admin.appointments.show', $appointment)
            ->with('status', 'appointment-created');
    }

    public function show(Appointment $appointment): View
    {
        $appointment->load([
            'customerProfile.user',
            'service',
            'staffProfile.user',
            'transactions.recorder',
            'feedback',
            'statusLogs.changedBy',
        ]);

        $formData = $this->formData($appointment);

        return view('admin.appointments.show', [
            'appointment' => $appointment,
            'transaction' => new Transaction([
                'appointment_id' => $appointment->id,
                'customer_profile_id' => $appointment->customer_profile_id,
                'service_id' => $appointment->service_id,
                'amount' => $appointment->service?->price,
                'payment_status' => Transaction::PAYMENT_PAID,
                'payment_method' => Transaction::METHOD_CASH,
                'paid_at' => now(),
            ]),
            'transactionAppointments' => collect([$appointment]),
            ...$formData,
        ]);
    }

    public function edit(Appointment $appointment): View
    {
        $appointment->load(['customerProfile.user', 'service', 'staffProfile.user']);

        return view('admin.appointments.edit', $this->formData($appointment));
    }

    public function update(AppointmentRequest $request, Appointment $appointment, AppointmentWorkflow $workflow): RedirectResponse
    {
        $this->persistAppointment($appointment, $request, $workflow);

        return redirect()
            ->route('admin.appointments.show', $appointment)
            ->with('status', 'appointment-updated');
    }

    private function persistAppointment(Appointment $appointment, AppointmentRequest $request, AppointmentWorkflow $workflow): Appointment
    {
        $data = $request->validated();
        $service = Service::query()->findOrFail($data['service_id']);
        $status = $data['status'] ?? $appointment->status ?: Appointment::STATUS_PENDING;
        $requestedStart = Carbon::parse($data['requested_start_at']);
        $scheduledStart = ! empty($data['scheduled_start_at']) ? Carbon::parse($data['scheduled_start_at']) : null;
        $staffProfile = ! empty($data['staff_profile_id']) ? StaffProfile::query()->with('user')->findOrFail($data['staff_profile_id']) : null;

        if (! in_array($status, Appointment::STATUSES, true)) {
            $status = Appointment::STATUS_PENDING;
        }

        if ($status === Appointment::STATUS_CONFIRMED) {
            if (! $staffProfile || ! $scheduledStart) {
                throw ValidationException::withMessages([
                    'scheduled_start_at' => __('Confirmed appointments require staff and scheduled time.'),
                ]);
            }

            $scheduledEnd = $workflow->scheduledEnd($scheduledStart, $service);

            if (! $workflow->isStaffAvailable($staffProfile, $service, $scheduledStart, $scheduledEnd, $appointment->exists ? $appointment : null)) {
                throw ValidationException::withMessages([
                    'scheduled_start_at' => __('Selected staff is not available for this confirmed schedule.'),
                ]);
            }
        } else {
            $scheduledEnd = $scheduledStart ? $workflow->scheduledEnd($scheduledStart, $service) : null;
        }

        $appointment->fill([
            'appointment_number' => $appointment->appointment_number ?: $workflow->nextAppointmentNumber(),
            'customer_profile_id' => $data['customer_profile_id'] ?? $appointment->customer_profile_id,
            'service_id' => $service->id,
            'staff_profile_id' => $staffProfile?->id,
            'requested_start_at' => $requestedStart,
            'scheduled_start_at' => $scheduledStart,
            'scheduled_end_at' => $scheduledEnd,
            'customer_notes' => $data['customer_notes'] ?? null,
            'internal_notes' => $data['internal_notes'] ?? null,
            'created_by' => $appointment->created_by ?: $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        if (! $appointment->customer_profile_id) {
            throw ValidationException::withMessages([
                'customer_profile_id' => __('Select a customer for this appointment.'),
            ]);
        }

        $appointment->save();
        $workflow->changeStatus($appointment, $status, $request->user()->id, $data['reason'] ?? null);

        return $appointment;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Appointment $appointment): array
    {
        return [
            'appointment' => $appointment,
            'customers' => CustomerProfile::query()->with('user')->get()->sortBy('user.name'),
            'services' => Service::query()->where('is_active', true)->orderBy('name')->get(),
            'staffProfiles' => StaffProfile::query()->with(['user', 'services'])->where('is_bookable', true)->get()->sortBy('user.name'),
        ];
    }
}
