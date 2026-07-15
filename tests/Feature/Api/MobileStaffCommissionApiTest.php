<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\StaffProfile;
use App\Models\TherapistCommission;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileStaffCommissionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_therapist_sees_only_personal_commissions_and_totals(): void
    {
        $staff = StaffProfile::factory()->create();
        $appointment = Appointment::factory()->for($staff, 'staffProfile')->create();
        $transaction = Transaction::factory()->for($appointment)->create();
        $mine = TherapistCommission::factory()->for($staff)->for($appointment)->for($transaction)->create(['commission_amount' => '220.00']);
        TherapistCommission::factory()->create(['commission_amount' => '999.00']);
        $token = $this->token($staff->user);

        $this->withToken($token)->getJson('/api/v1/staff/commissions')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('summary.pending', '220.00')->assertJsonPath('summary.net', '220.00');
        $this->withToken($token)->getJson("/api/v1/staff/commissions/{$mine->id}")->assertOk()
            ->assertJsonPath('data.appointment.id', $appointment->id);
    }

    public function test_therapist_cannot_open_another_therapists_commission(): void
    {
        $staff = StaffProfile::factory()->create();
        $other = TherapistCommission::factory()->create();

        $this->withToken($this->token($staff->user))->getJson("/api/v1/staff/commissions/{$other->id}")->assertForbidden();
    }

    private function token(User $user): string
    {
        return $user->createToken('android:test', ['mobile'], now()->addDays(30))->plainTextToken;
    }
}
