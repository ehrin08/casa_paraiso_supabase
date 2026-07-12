<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\AppointmentWorkflow;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RepairApprovedAppointmentReferences extends Command
{
    protected $signature = 'casa:repair-approved-appointment-references
        {--actor=2 : Admin user ID recorded as the repair actor}
        {--confirmed=APT-DEMO-CONFIRMED : Confirmed appointment to reassign}
        {--staff=2 : Active eligible staff profile receiving the confirmed appointment}
        {--pending=APT-DEMO-PENDING : Pending appointment whose deleted preference is cleared}
        {--execute : Apply the approved changes; otherwise perform a dry run}';

    protected $description = 'Safely apply the explicitly approved appointment staff-reference repair';

    public function handle(AppointmentWorkflow $workflow): int
    {
        $actorId = (int) $this->option('actor');
        $staffId = (int) $this->option('staff');
        $confirmedNumber = (string) $this->option('confirmed');
        $pendingNumber = (string) $this->option('pending');

        try {
            $this->validateCurrentState($workflow, $actorId, $staffId, $confirmedNumber, $pendingNumber);

            if (! $this->option('execute')) {
                $this->components->info('Dry run passed. No data was changed.');
                $this->line("Would reassign {$confirmedNumber} to staff profile {$staffId}.");
                $this->line("Would clear the deleted preference from {$pendingNumber}.");

                return self::SUCCESS;
            }

            DB::transaction(function () use ($workflow, $actorId, $staffId, $confirmedNumber, $pendingNumber): void {
                $staff = StaffProfile::query()
                    ->with(['user', 'services', 'weeklySchedules', 'scheduleExceptions'])
                    ->lockForUpdate()
                    ->findOrFail($staffId);
                $confirmed = Appointment::query()
                    ->where('appointment_number', $confirmedNumber)
                    ->lockForUpdate()
                    ->firstOrFail();
                $pending = Appointment::query()
                    ->where('appointment_number', $pendingNumber)
                    ->lockForUpdate()
                    ->firstOrFail();
                $service = Service::withTrashed()->findOrFail($confirmed->service_id);

                $this->assertRepairable($workflow, $confirmed, $pending, $staff, $service);

                $confirmed->internal_notes = $this->appendRepairNote(
                    $confirmed->internal_notes,
                    "Approved data repair: reassigned from deleted staff profile 1 to staff profile {$staffId}.",
                );

                $workflow->schedule(
                    $confirmed,
                    $staff,
                    $service,
                    $confirmed->scheduled_start_at,
                    $actorId,
                    'Approved CRUD remediation for a deleted therapist reference.',
                );

                $pending->forceFill([
                    'preferred_staff_profile_id' => null,
                    'updated_by' => $actorId,
                    'internal_notes' => $this->appendRepairNote(
                        $pending->internal_notes,
                        'Approved data repair: cleared deleted preferred staff profile 1; request remains pending.',
                    ),
                ])->save();
            }, 3);
        } catch (\Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Approved appointment reference repair completed.');

        return self::SUCCESS;
    }

    private function validateCurrentState(
        AppointmentWorkflow $workflow,
        int $actorId,
        int $staffId,
        string $confirmedNumber,
        string $pendingNumber,
    ): void {
        $actor = User::query()->findOrFail($actorId);

        if (! $actor->isAdmin() || ! $actor->is_active) {
            throw new RuntimeException('The repair actor must be an active administrator.');
        }

        $staff = StaffProfile::query()
            ->with(['user', 'services', 'weeklySchedules', 'scheduleExceptions'])
            ->findOrFail($staffId);
        $confirmed = Appointment::query()
            ->with('staffProfile')
            ->where('appointment_number', $confirmedNumber)
            ->firstOrFail();
        $pending = Appointment::query()
            ->with('preferredStaffProfile')
            ->where('appointment_number', $pendingNumber)
            ->firstOrFail();
        $service = Service::withTrashed()->findOrFail($confirmed->service_id);

        $this->assertRepairable($workflow, $confirmed, $pending, $staff, $service);
    }

    private function assertRepairable(
        AppointmentWorkflow $workflow,
        Appointment $confirmed,
        Appointment $pending,
        StaffProfile $staff,
        Service $service,
    ): void {
        if ($confirmed->status !== Appointment::STATUS_CONFIRMED
            || ! $confirmed->scheduled_start_at
            || ! $confirmed->scheduled_end_at
            || ! $confirmed->staffProfile?->trashed()) {
            throw new RuntimeException('The confirmed appointment no longer matches the approved repair conditions.');
        }

        if ($pending->status !== Appointment::STATUS_PENDING
            || ! $pending->preferredStaffProfile?->trashed()
            || $pending->staff_profile_id
            || $pending->scheduled_start_at
            || $pending->scheduled_end_at) {
            throw new RuntimeException('The pending appointment no longer matches the approved repair conditions.');
        }

        $workflow->assertBookableStart($confirmed->scheduled_start_at, $service);

        if (! $workflow->isStaffAvailable(
            $staff,
            $service,
            $confirmed->scheduled_start_at,
            $confirmed->scheduled_end_at,
            $confirmed,
        )) {
            throw new RuntimeException('The receiving therapist is no longer eligible and available for the confirmed appointment.');
        }
    }

    private function appendRepairNote(?string $notes, string $repairNote): string
    {
        $notes = trim((string) $notes);

        return $notes === '' ? $repairNote : $notes."\n\n".$repairNote;
    }
}
