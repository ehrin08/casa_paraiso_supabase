<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Feedback;
use App\Models\PromotionSuggestion;
use App\Models\StaffProfile;
use App\Models\TherapistCommission;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAdminInsightsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_review_feedback_commissions_rewards_and_reports(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $this->token($admin);
        $feedback = Feedback::factory()->create(['sentiment_label' => Feedback::SENTIMENT_POSITIVE]);
        $staff = StaffProfile::factory()->create();
        $appointment = Appointment::factory()->for($staff, 'staffProfile')->create();
        $transaction = Transaction::factory()->for($appointment)->create();
        $commission = TherapistCommission::factory()->for($staff)->for($appointment)->for($transaction)->create(['commission_amount' => '220.00']);
        $reward = PromotionSuggestion::factory()->create(['status' => PromotionSuggestion::STATUS_SUGGESTED, 'expires_at' => now()->addDays(30)]);

        $this->withToken($token)->getJson('/api/v1/admin/feedback')->assertOk()
            ->assertJsonPath('data.0.id', $feedback->id)->assertJsonPath('summary.positive', 1);
        $this->withToken($token)->getJson('/api/v1/admin/commissions')->assertOk()
            ->assertJsonPath('data.0.id', $commission->id)->assertJsonPath('data.0.therapist.id', $staff->id);
        $this->withToken($token)->getJson('/api/v1/admin/promotions')->assertOk()
            ->assertJsonPath('data.0.id', $reward->id);
        $this->withToken($token)->getJson('/api/v1/admin/reports?type=appointments')->assertOk()
            ->assertJsonPath('type', 'appointments')->assertJsonPath('meta.per_page', 15);
        $this->withToken($token)->get('/api/v1/admin/reports/export?type=appointments')->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_admin_can_record_commission_payout_and_dismiss_available_reward(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $this->token($admin);
        $commission = TherapistCommission::factory()->create(['status' => TherapistCommission::STATUS_PENDING, 'commission_amount' => '100.00']);
        $reward = PromotionSuggestion::factory()->create(['status' => PromotionSuggestion::STATUS_SUGGESTED, 'expires_at' => now()->addDays(30)]);

        $this->withToken($token)->patchJson("/api/v1/admin/commissions/{$commission->id}/pay", ['paid_at' => today()->toDateString(), 'notes' => 'Paid in cash.'])
            ->assertOk()->assertJsonPath('data.status', TherapistCommission::STATUS_PAID);
        $this->withToken($token)->patchJson("/api/v1/admin/promotions/{$reward->id}/dismiss")
            ->assertOk();
        $this->assertDatabaseHas('promotion_suggestions', ['id' => $reward->id, 'status' => PromotionSuggestion::STATUS_DISMISSED]);
    }

    public function test_admin_can_read_and_update_business_settings(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $this->token($admin);

        $this->withToken($token)->getJson('/api/v1/admin/settings')->assertOk()
            ->assertJsonPath('data.operating.timezone', 'Asia/Manila')
            ->assertJsonPath('data.settings.business_address', 'Barangay Cuta East, Santa Teresita, Batangas, Philippines')
            ->assertJsonPath('data.settings.messenger_url', 'https://m.me/61579320037378');
        $this->withToken($token)->patchJson('/api/v1/admin/settings', [
            'business_name' => 'Casa Paraiso Mobile',
            'contact_email' => 'hello@example.test',
            'contact_phone' => '09171234567',
            'business_address' => 'San Pablo City',
            'location_landmarks' => 'Near the plaza.',
            'facebook_url' => 'https://www.facebook.com/61579320037378',
            'messenger_url' => 'https://m.me/61579320037378',
            'map_url' => 'https://www.google.com/maps/search/?api=1&query=Casa+Paraiso',
            'default_payment_method' => Transaction::METHOD_CASH,
        ])->assertOk()
            ->assertJsonPath('message', 'Business settings updated.');
        $this->assertDatabaseHas('application_settings', ['business_name' => 'Casa Paraiso Mobile', 'location_landmarks' => 'Near the plaza.']);
    }

    private function token(User $user): string
    {
        return $user->createToken('android:test', ['mobile'], now()->addDays(30))->plainTextToken;
    }
}
