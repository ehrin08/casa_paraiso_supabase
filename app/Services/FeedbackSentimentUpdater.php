<?php

namespace App\Services;

use App\Models\Feedback;

class FeedbackSentimentUpdater
{
    public function persist(Feedback $feedback, array $analysis): Feedback
    {
        $feedback->forceFill([
            'sentiment_label' => $analysis['label'],
            'sentiment_score' => $analysis['score'],
            'sentiment_analysis_version' => $analysis['version'],
            'sentiment_evidence' => $analysis['evidence'],
        ])->save();

        $feedback->topics()->delete();
        $feedback->topics()->createMany(array_map(
            fn (array $topic): array => [
                'topic_key' => $topic['key'],
                'polarity' => $topic['polarity'],
                'matched_terms' => $topic['matched_terms'],
            ],
            $analysis['topics'] ?? [],
        ));

        return $feedback->load('topics');
    }
}
