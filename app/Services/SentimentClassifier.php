<?php

namespace App\Services;

use App\Models\Feedback;

class SentimentClassifier
{
    /**
     * @return array{label: string, score: float}
     */
    public function classify(int $rating, ?string $comment): array
    {
        $label = match (true) {
            $rating >= 4 => Feedback::SENTIMENT_POSITIVE,
            $rating === 3 => Feedback::SENTIMENT_NEUTRAL,
            default => Feedback::SENTIMENT_NEGATIVE,
        };

        $comment = strtolower((string) $comment);

        $negativeWords = ['bad', 'late', 'rude', 'dirty', 'painful', 'poor', 'disappointed'];
        $positiveWords = ['great', 'relaxing', 'clean', 'friendly', 'excellent', 'amazing', 'satisfied'];

        foreach ($negativeWords as $word) {
            if (str_contains($comment, $word)) {
                $label = Feedback::SENTIMENT_NEGATIVE;
                break;
            }
        }

        if ($label !== Feedback::SENTIMENT_NEGATIVE) {
            foreach ($positiveWords as $word) {
                if (str_contains($comment, $word)) {
                    $label = Feedback::SENTIMENT_POSITIVE;
                    break;
                }
            }
        }

        $score = match ($label) {
            Feedback::SENTIMENT_POSITIVE => 1.0,
            Feedback::SENTIMENT_NEGATIVE => -1.0,
            default => 0.0,
        };

        return ['label' => $label, 'score' => $score];
    }
}
