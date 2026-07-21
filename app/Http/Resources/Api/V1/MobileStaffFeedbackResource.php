<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileStaffFeedbackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'sentiment' => $this->sentiment_label,
            'analysis_version' => $this->sentiment_analysis_version,
            'evidence' => $this->sentiment_evidence,
            'topics' => $this->topics->map(fn ($topic): array => ['key' => $topic->topic_key, 'polarity' => $topic->polarity, 'matched_terms' => $topic->matched_terms ?? []])->values(),
            'submitted_at' => $this->submitted_at?->copy()->timezone(config('app.timezone'))->toIso8601String(),
            'customer' => $this->customerProfile ? ['id' => $this->customerProfile->id, 'name' => $this->customerProfile->user?->name] : null,
            'service' => $this->service ? ['id' => $this->service->id, 'name' => $this->service->name] : null,
            'appointment' => $this->appointment ? ['id' => $this->appointment->id, 'appointment_number' => $this->appointment->appointment_number] : null,
        ];
    }
}
