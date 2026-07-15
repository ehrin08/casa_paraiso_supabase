<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\PromotionSuggestion;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWeeklySchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileCustomerBookingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_options_include_only_active_services_eligible_therapists_addons_and_owned_vouchers(): void
    {
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['name' => 'GAIA TOUCH', 'price' => '499.00', 'is_active' => true]);
        Service::factory()->create(['name' => 'Hidden service', 'is_active' => false]);
        $eligible = StaffProfile::factory()->create();
        $eligible->services()->attach($service);
        StaffProfile::factory()->create();
        $voucher = PromotionSuggestion::factory()->for($customer)->create([
            'addon_code' => 'hot-compress',
            'status' => PromotionSuggestion::STATUS_SUGGESTED,
        ]);

        $response = $this->withToken($this->token($customer->user))
            ->getJson('/api/v1/customer/booking-options');

        $response->assertOk()
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertJsonCount(1, 'data.services')
            ->assertJsonPath('data.services.0.name', 'GAIA TOUCH')
            ->assertJsonPath('data.services.0.price', '499.00')
            ->assertJsonPath('data.services.0.therapists.0.id', $eligible->id)
            ->assertJsonPath('data.vouchers.0.id', $voucher->id)
            ->assertJsonPath('data.vouchers.0.name', 'Hot Compress')
            ->assertJsonPath('data.booking_window.timezone', 'Asia/Manila');

        $this->assertSame(
            ['ventosa', 'hot-compress', 'hot-stone', 'back-massage-30', 'vip-room'],
            collect($response->json('data.addons'))->pluck('code')->all(),
        );
    }

    public function test_customer_can_load_offset_aware_availability_and_confirm_a_booking_with_addon_and_voucher(): void
    {
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create([
            'name' => 'GAIA TOUCH',
            'duration_minutes' => 60,
            'price' => '499.00',
            'is_active' => true,
        ]);
        $staff = StaffProfile::factory()->create();
        $staff->services()->attach($service);
        $start = now('Asia/Manila')->addWeek()->setTime(14, 0, 0);
        StaffWeeklySchedule::factory()->for($staff)->create([
            'day_of_week' => $start->dayOfWeek,
            'start_time' => '13:00:00',
            'end_time' => '18:00:00',
        ]);
        $voucher = PromotionSuggestion::factory()->for($customer)->create([
            'addon_code' => 'hot-compress',
            'status' => PromotionSuggestion::STATUS_SUGGESTED,
        ]);
        $token = $this->token($customer->user);
        $query = http_build_query([
            'service_id' => $service->id,
            'preferred_staff_profile_id' => $staff->id,
            'promotion_suggestion_id' => $voucher->id,
            'addon_codes' => ['back-massage-30'],
            'month' => $start->format('Y-m'),
        ]);

        $availability = $this->withToken($token)->getJson('/api/v1/customer/availability?'.$query);
        $availability->assertOk();
        $slot = collect($availability->json('data.dates.'.$start->toDateString()))->firstWhere('time', '14:00');

        $this->assertNotNull($slot);
        $this->assertStringEndsWith('+08:00', $slot['starts_at']);
        $this->assertStringEndsWith('+08:00', $slot['ends_at']);

        $this->withToken($token)->postJson('/api/v1/customer/appointments', [
            'service_id' => $service->id,
            'preferred_staff_profile_id' => $staff->id,
            'promotion_suggestion_id' => $voucher->id,
            'addon_codes' => ['back-massage-30'],
            'requested_start_at' => $slot['starts_at'],
            'customer_notes' => 'Please focus on my shoulders.',
        ])->assertCreated()
            ->assertJsonPath('data.status', Appointment::STATUS_CONFIRMED)
            ->assertJsonPath('data.therapist.id', $staff->id)
            ->assertJsonPath('data.voucher.id', $voucher->id)
            ->assertJsonPath('data.addons.0.code', 'back-massage-30')
            ->assertJsonPath('data.expected_amount', '798.00')
            ->assertJsonPath('message', 'Appointment confirmed and added to the schedule.');

        $appointment = Appointment::query()->sole();
        $this->assertTrue($appointment->scheduled_end_at->equalTo($start->copy()->addMinutes(90)));
        $this->assertDatabaseHas('promotion_suggestions', [
            'id' => $voucher->id,
            'status' => PromotionSuggestion::STATUS_APPLIED,
        ]);
        $this->assertDatabaseHas('appointment_addons', [
            'appointment_id' => $appointment->id,
            'addon_code' => 'back-massage-30',
            'price' => '299.00',
        ]);
    }

    public function test_booking_rechecks_capacity_and_returns_stable_validation_errors(): void
    {
        $firstCustomer = CustomerProfile::factory()->create();
        $secondCustomer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 60, 'is_active' => true]);
        $staff = StaffProfile::factory()->create();
        $staff->services()->attach($service);
        $start = now('Asia/Manila')->addWeek()->setTime(14, 0, 0);
        StaffWeeklySchedule::factory()->for($staff)->create([
            'day_of_week' => $start->dayOfWeek,
            'start_time' => '13:00:00',
            'end_time' => '18:00:00',
        ]);
        $payload = [
            'service_id' => $service->id,
            'preferred_staff_profile_id' => $staff->id,
            'requested_start_at' => $start->toIso8601String(),
        ];

        $this->withToken($this->token($firstCustomer->user))
            ->postJson('/api/v1/customer/appointments', $payload)
            ->assertCreated();

        $this->withToken($this->token($secondCustomer->user))
            ->postJson('/api/v1/customer/appointments', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['fields' => ['requested_start_at']]]);

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_mobile_api_failures_use_stable_error_envelopes(): void
    {
        $customer = CustomerProfile::factory()->create();

        $this->getJson('/api/v1/customer/booking-options')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->withToken($this->token($customer->user))
            ->getJson('/api/v1/customer/availability?month=invalid')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['fields' => ['service_id', 'month']]]);

    }

    public function test_customer_booking_routes_reject_other_roles(): void
    {
        $staff = User::factory()->staff()->create(['email_verified_at' => now(), 'is_active' => true]);

        $this->withToken($this->token($staff))
            ->getJson('/api/v1/customer/booking-options')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    private function token(User $user): string
    {
        return $user->createToken('android:test', ['mobile'], now()->addDays(30))->plainTextToken;
    }
}
