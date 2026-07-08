<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\PromotionRule;
use App\Models\PromotionSuggestion;
use App\Models\RfmSegment;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RfmPromotionGenerator
{
    /**
     * @return Collection<int, PromotionSuggestion>
     */
    public function generate(?CustomerProfile $onlyCustomer = null): Collection
    {
        $customers = CustomerProfile::query()
            ->with('user')
            ->when($onlyCustomer, fn ($query) => $query->whereKey($onlyCustomer->getKey()))
            ->get();

        return $customers
            ->map(fn (CustomerProfile $customer) => $this->generateForCustomer($customer))
            ->filter();
    }

    public function generateForCustomer(CustomerProfile $customer): ?PromotionSuggestion
    {
        $metrics = $this->metrics($customer);
        $segment = $this->matchingSegment($metrics);

        if (! $segment) {
            return null;
        }

        $rule = PromotionRule::query()
            ->where('rfm_segment_id', $segment->id)
            ->where('is_active', true)
            ->oldest()
            ->first();

        if (! $rule || ! $rule->suggested_offer) {
            return null;
        }

        return PromotionSuggestion::query()->create([
            'customer_profile_id' => $customer->id,
            'rfm_segment_id' => $segment->id,
            'promotion_rule_id' => $rule->id,
            'recency_days' => $metrics['recency_days'],
            'frequency_count' => $metrics['frequency_count'],
            'monetary_total' => $metrics['monetary_total'],
            'suggested_offer' => $rule->suggested_offer,
            'status' => PromotionSuggestion::STATUS_SUGGESTED,
        ]);
    }

    /**
     * @return array{recency_days: int|null, frequency_count: int, monetary_total: float}
     */
    private function metrics(CustomerProfile $customer): array
    {
        $paidTransactions = Transaction::query()
            ->where('customer_profile_id', $customer->id)
            ->where('payment_status', Transaction::PAYMENT_PAID)
            ->whereHas('appointment', fn ($query) => $query->where('status', Appointment::STATUS_COMPLETED));

        $latestPaidAt = (clone $paidTransactions)->max('paid_at');

        return [
            'recency_days' => $latestPaidAt ? (int) now()->diffInDays(Carbon::parse($latestPaidAt)) : null,
            'frequency_count' => (clone $paidTransactions)->count(),
            'monetary_total' => (float) (clone $paidTransactions)->sum('amount'),
        ];
    }

    /**
     * @param array{recency_days: int|null, frequency_count: int, monetary_total: float} $metrics
     */
    private function matchingSegment(array $metrics): ?RfmSegment
    {
        return RfmSegment::query()
            ->where('is_active', true)
            ->orderByDesc('monetary_min')
            ->orderByDesc('frequency_min')
            ->get()
            ->first(function (RfmSegment $segment) use ($metrics): bool {
                $recency = $metrics['recency_days'];

                if ($segment->recency_min_days !== null && ($recency === null || $recency < $segment->recency_min_days)) {
                    return false;
                }

                if ($segment->recency_max_days !== null && ($recency === null || $recency > $segment->recency_max_days)) {
                    return false;
                }

                if ($segment->frequency_min !== null && $metrics['frequency_count'] < $segment->frequency_min) {
                    return false;
                }

                if ($segment->frequency_max !== null && $metrics['frequency_count'] > $segment->frequency_max) {
                    return false;
                }

                if ($segment->monetary_min !== null && $metrics['monetary_total'] < (float) $segment->monetary_min) {
                    return false;
                }

                if ($segment->monetary_max !== null && $metrics['monetary_total'] > (float) $segment->monetary_max) {
                    return false;
                }

                return true;
            });
    }
}
