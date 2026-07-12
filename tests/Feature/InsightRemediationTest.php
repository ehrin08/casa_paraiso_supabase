<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Feedback;
use App\Models\PromotionRule;
use App\Models\PromotionSuggestion;
use App\Models\RfmSegment;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RfmPromotionGenerator;
use App\Services\SentimentClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InsightRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_sentiment_uses_token_boundaries_negation_and_mixed_polarity(): void
    {
        $classifier = app(SentimentClassifier::class);

        $this->assertSame(Feedback::SENTIMENT_POSITIVE, $classifier->classify(5, 'Badminton was fun.')['label']);
        $this->assertSame(Feedback::SENTIMENT_NEGATIVE, $classifier->classify(5, 'The room was not very clean.')['label']);
        $this->assertSame(Feedback::SENTIMENT_POSITIVE, $classifier->classify(3, 'The service was not bad.')['label']);
        $this->assertSame(Feedback::SENTIMENT_NEUTRAL, $classifier->classify(5, 'Great treatment but rude checkout.')['label']);
    }

    public function test_feedback_enforces_ownership_and_returns_validation_for_duplicate_submission(): void
    {
        $customerUser = User::factory()->customer()->create();
        $customer = CustomerProfile::factory()->for($customerUser)->create();
        $otherUser = User::factory()->customer()->create();
        CustomerProfile::factory()->for($otherUser)->create();
        $service = Service::factory()->create();
        $appointment = Appointment::factory()
            ->for($customer)
            ->for($service)
            ->create([
                'status' => Appointment::STATUS_COMPLETED,
                'completed_at' => now()->subHour(),
            ]);

        $payload = [
            'appointment_id' => $appointment->id,
            'rating' => 5,
            'comment' => 'Not bad and very relaxing.',
        ];

        $this->actingAs($otherUser)
            ->post(route('customer.feedback.store', absolute: false), $payload)
            ->assertNotFound();

        $this->actingAs($customerUser)
            ->post(route('customer.feedback.store', absolute: false), $payload)
            ->assertRedirect(route('customer.feedback.index', absolute: false));

        $this->actingAs($customerUser)
            ->from(route('customer.feedback.create', absolute: false))
            ->post(route('customer.feedback.store', absolute: false), $payload)
            ->assertRedirect(route('customer.feedback.create', absolute: false))
            ->assertSessionHasErrors('appointment_id');

        $this->assertDatabaseCount('feedback', 1);
    }

    public function test_zero_visit_generation_is_idempotent_for_the_same_metrics_and_rule(): void
    {
        $customer = CustomerProfile::factory()->create();
        $segment = RfmSegment::factory()->create([
            'name' => 'New customer',
            'recency_min_days' => null,
            'recency_max_days' => 30,
            'frequency_min' => 0,
            'frequency_max' => 0,
            'monetary_min' => 0,
            'monetary_max' => 0,
            'is_active' => true,
        ]);
        $rule = PromotionRule::factory()->for($segment, 'rfmSegment')->create([
            'suggested_offer' => 'Welcome add-on',
            'is_active' => true,
        ]);
        $generator = app(RfmPromotionGenerator::class);

        $first = $generator->generate($customer);
        $second = $generator->generate($customer);

        $this->assertCount(1, $first);
        $this->assertCount(0, $second);
        $this->assertDatabaseCount('promotion_suggestions', 1);
        $this->assertDatabaseHas('promotion_suggestions', [
            'customer_profile_id' => $customer->id,
            'rfm_segment_id' => $segment->id,
            'promotion_rule_id' => $rule->id,
            'recency_days' => null,
            'frequency_count' => 0,
            'monetary_total' => '0.00',
            'suggested_offer' => 'Welcome add-on',
        ]);
        $this->assertNotNull(PromotionSuggestion::query()->sole()->generation_key);
    }

    public function test_promotion_status_timestamps_change_only_on_real_transitions_and_notes_can_be_cleared(): void
    {
        $admin = User::factory()->admin()->create();
        $appliedAt = Carbon::parse('2026-07-01 10:00:00');
        $suggestion = PromotionSuggestion::factory()->create([
            'status' => PromotionSuggestion::STATUS_APPLIED,
            'reviewed_by' => $admin->id,
            'reviewed_at' => $appliedAt,
            'applied_at' => $appliedAt,
            'dismissed_at' => null,
            'notes' => 'Keep me for now.',
        ]);

        Carbon::setTestNow('2026-07-12 12:00:00');

        $this->actingAs($admin)
            ->patch(route('admin.promotions.update', $suggestion, false), [
                'status' => PromotionSuggestion::STATUS_APPLIED,
                'notes' => 'Still applied.',
            ])
            ->assertRedirect(route('admin.promotions.show', $suggestion, false));

        $this->assertTrue($suggestion->fresh()->applied_at->equalTo($appliedAt));

        $this->actingAs($admin)
            ->patch(route('admin.promotions.update', $suggestion, false), [
                'status' => PromotionSuggestion::STATUS_DISMISSED,
                'notes' => 'Dismissed.',
            ])
            ->assertRedirect();

        $suggestion->refresh();
        $this->assertNull($suggestion->applied_at);
        $this->assertTrue($suggestion->dismissed_at->equalTo(now()));

        $this->actingAs($admin)
            ->patch(route('admin.promotions.update', $suggestion, false), [
                'status' => PromotionSuggestion::STATUS_REVIEWED,
                'notes' => '',
            ])
            ->assertRedirect();

        $suggestion->refresh();
        $this->assertNull($suggestion->applied_at);
        $this->assertNull($suggestion->dismissed_at);
        $this->assertNull($suggestion->notes);

    }

    public function test_admin_can_manage_segments_and_rules_with_validation_and_role_enforcement(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)
            ->get(route('admin.rfm-segments.index', absolute: false))
            ->assertForbidden();

        $this->actingAs($admin)
            ->from(route('admin.rfm-segments.create', absolute: false))
            ->post(route('admin.rfm-segments.store', absolute: false), [
                'name' => 'Invalid range',
                'frequency_min' => 5,
                'frequency_max' => 2,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.rfm-segments.create', absolute: false))
            ->assertSessionHasErrors('frequency_max');

        $this->actingAs($admin)
            ->post(route('admin.rfm-segments.store', absolute: false), [
                'name' => 'First-time visitor',
                'description' => 'No completed paid visits.',
                'frequency_min' => 0,
                'frequency_max' => 0,
                'monetary_min' => 0,
                'monetary_max' => 0,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.rfm-segments.index', absolute: false));

        $segment = RfmSegment::query()->sole();

        $this->actingAs($admin)
            ->post(route('admin.promotion-rules.store', absolute: false), [
                'rfm_segment_id' => $segment->id,
                'name' => 'Welcome rule',
                'suggested_offer' => 'Free welcome add-on',
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.promotion-rules.index', absolute: false));

        $rule = PromotionRule::query()->sole();

        $this->actingAs($admin)
            ->patch(route('admin.promotion-rules.update', $rule, false), [
                'rfm_segment_id' => $segment->id,
                'name' => 'Welcome rule updated',
                'suggested_offer' => 'Free tea service',
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.promotion-rules.index', absolute: false));

        $this->actingAs($admin)
            ->patch(route('admin.promotion-rules.toggle', $rule, false))
            ->assertRedirect();

        $this->assertFalse($rule->fresh()->is_active);

        $this->actingAs($admin)
            ->patch(route('admin.rfm-segments.toggle', $segment, false))
            ->assertRedirect();

        $this->assertFalse($segment->fresh()->is_active);
    }

    public function test_report_dates_use_displayed_business_fields_and_customer_filters_show_all_metrics(): void
    {
        $admin = User::factory()->admin()->create();
        $customerUser = User::factory()->customer()->create([
            'name' => 'Report Customer',
            'is_active' => true,
        ]);
        $customer = CustomerProfile::factory()->for($customerUser)->create([
            'created_at' => '2026-02-05 09:00:00',
            'updated_at' => '2026-02-05 09:00:00',
        ]);
        $service = Service::factory()->create(['name' => 'Report Service']);
        $appointment = Appointment::factory()
            ->for($customer)
            ->for($service)
            ->create([
                'appointment_number' => 'APT-REPORT-DATE',
                'status' => Appointment::STATUS_CONFIRMED,
                'requested_start_at' => '2026-03-01 14:00:00',
                'scheduled_start_at' => '2026-03-05 14:00:00',
                'scheduled_end_at' => '2026-03-05 15:00:00',
                'confirmed_at' => '2026-02-28 12:00:00',
            ]);
        $transaction = Transaction::factory()
            ->for($customer)
            ->for($appointment)
            ->for($service)
            ->for($admin, 'recorder')
            ->create([
                'transaction_number' => 'TRX-REPORT-DATE',
                'paid_at' => '2026-03-10 10:00:00',
                'created_at' => '2026-03-02 10:00:00',
                'updated_at' => '2026-03-02 10:00:00',
            ]);
        Feedback::factory()
            ->for($customer)
            ->for($appointment)
            ->for($service)
            ->create();
        PromotionSuggestion::factory()->for($customer)->create();

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'type' => 'appointments',
                'date_from' => '2026-03-05',
                'date_to' => '2026-03-05',
            ], false))
            ->assertOk()
            ->assertDontSee('@js(', false)
            ->assertSee('APT-REPORT-DATE');

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'type' => 'appointments',
                'date_from' => '2026-03-01',
                'date_to' => '2026-03-01',
            ], false))
            ->assertOk()
            ->assertDontSee('APT-REPORT-DATE');

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'type' => 'transactions',
                'date_from' => '2026-03-10',
                'date_to' => '2026-03-10',
            ], false))
            ->assertOk()
            ->assertSee('TRX-REPORT-DATE');

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'type' => 'transactions',
                'date_from' => '2026-03-02',
                'date_to' => '2026-03-02',
            ], false))
            ->assertOk()
            ->assertDontSee('TRX-REPORT-DATE');

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'type' => 'customers',
                'status' => 'active',
                'date_from' => '2026-02-05',
                'date_to' => '2026-02-05',
            ], false))
            ->assertOk()
            ->assertSee('Report Customer')
            ->assertSee('1 appointment')
            ->assertSee('1 transaction')
            ->assertSee('1 feedback')
            ->assertSee('1 promotion');

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'type' => 'customers',
                'status' => 'inactive',
                'date_from' => '2026-02-05',
                'date_to' => '2026-02-05',
            ], false))
            ->assertOk()
            ->assertDontSee('Report Customer');

        $this->actingAs($admin)
            ->from(route('admin.reports.index', absolute: false))
            ->get(route('admin.reports.index', [
                'type' => 'appointments',
                'date_from' => '2026-03-10',
                'date_to' => '2026-03-01',
            ], false))
            ->assertRedirect(route('admin.reports.index', absolute: false))
            ->assertSessionHasErrors('date_to');

        $this->assertSame('2026-03-10', $transaction->paid_at->toDateString());
    }

    public function test_csv_export_neutralizes_formula_values_and_streams_beyond_the_old_limit(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['=SUM(1,1)', '+cmd', '-cmd', '@cmd'] as $index => $name) {
            $user = User::factory()->customer()->create(['name' => $name]);
            CustomerProfile::factory()->for($user)->create(['customer_code' => 'CP-FORMULA-'.$index]);
        }

        $customer = CustomerProfile::factory()->create();
        $segment = RfmSegment::factory()->create();
        $rule = PromotionRule::factory()->for($segment, 'rfmSegment')->create();
        $now = now()->format('Y-m-d H:i:s');
        $rows = [];

        for ($index = 1; $index <= 5001; $index++) {
            $rows[] = [
                'customer_profile_id' => $customer->id,
                'rfm_segment_id' => $segment->id,
                'promotion_rule_id' => $rule->id,
                'generation_key' => null,
                'recency_days' => 10,
                'frequency_count' => 2,
                'monetary_total' => 1000,
                'suggested_offer' => $index === 5001 ? 'LAST-ROW-MARKER' : 'Bulk offer '.$index,
                'status' => PromotionSuggestion::STATUS_SUGGESTED,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'applied_at' => null,
                'dismissed_at' => null,
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) === 500) {
                DB::table('promotion_suggestions')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('promotion_suggestions')->insert($rows);
        }

        $customerCsv = $this->actingAs($admin)
            ->get(route('admin.reports.export', ['type' => 'customers'], false))
            ->assertOk()
            ->assertDownload()
            ->streamedContent();

        foreach (["'=SUM(1,1)", "'+cmd", "'-cmd", "'@cmd"] as $safeValue) {
            $this->assertStringContainsString($safeValue, $customerCsv);
        }

        $promotionCsv = $this->actingAs($admin)
            ->get(route('admin.reports.export', ['type' => 'promotions'], false))
            ->assertOk()
            ->assertDownload()
            ->streamedContent();

        $this->assertStringContainsString('LAST-ROW-MARKER', $promotionCsv);
        $this->assertSame(5002, substr_count(trim($promotionCsv), "\n") + 1);
    }
}
