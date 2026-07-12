<?php

namespace App\Services;

use App\Models\Feedback;

class SentimentClassifier
{
    private const NEGATIVE_WORDS = ['bad', 'late', 'rude', 'dirty', 'painful', 'poor', 'disappointed'];

    private const POSITIVE_WORDS = ['great', 'relaxing', 'clean', 'friendly', 'excellent', 'amazing', 'satisfied'];

    private const NEGATIONS = [
        'not', 'no', 'never', 'neither', 'nor', 'hardly', 'barely', 'scarcely',
        "isn't", 'isnt', "wasn't", 'wasnt', "weren't", 'werent', "didn't", 'didnt',
        "doesn't", 'doesnt', "don't", 'dont', "can't", 'cant', "couldn't", 'couldnt',
    ];

    private const CONTRAST_WORDS = ['but', 'however', 'although', 'though', 'yet'];

    /**
     * @return array{label: string, score: float}
     */
    public function classify(int $rating, ?string $comment): array
    {
        $ratingLabel = match (true) {
            $rating >= 4 => Feedback::SENTIMENT_POSITIVE,
            $rating === 3 => Feedback::SENTIMENT_NEUTRAL,
            default => Feedback::SENTIMENT_NEGATIVE,
        };

        $tokens = $this->tokens($comment);
        $lexicalScore = 0;
        $matchedWords = 0;

        foreach ($tokens as $index => $token) {
            $polarity = match (true) {
                in_array($token, self::POSITIVE_WORDS, true) => 1,
                in_array($token, self::NEGATIVE_WORDS, true) => -1,
                default => 0,
            };

            if ($polarity === 0) {
                continue;
            }

            $matchedWords++;
            $lexicalScore += $this->isNegated($tokens, $index) ? -$polarity : $polarity;
        }

        $label = match (true) {
            $matchedWords === 0 => $ratingLabel,
            $lexicalScore < 0 => Feedback::SENTIMENT_NEGATIVE,
            $lexicalScore > 0 && $ratingLabel !== Feedback::SENTIMENT_NEGATIVE => Feedback::SENTIMENT_POSITIVE,
            $lexicalScore > 0 => $ratingLabel,
            default => Feedback::SENTIMENT_NEUTRAL,
        };

        $score = match ($label) {
            Feedback::SENTIMENT_POSITIVE => 1.0,
            Feedback::SENTIMENT_NEGATIVE => -1.0,
            default => 0.0,
        };

        return ['label' => $label, 'score' => $score];
    }

    /**
     * @return array<int, string>
     */
    private function tokens(?string $comment): array
    {
        preg_match_all("/[\\p{L}\\p{N}']+/u", mb_strtolower((string) $comment), $matches);

        return $matches[0] ?? [];
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function isNegated(array $tokens, int $wordIndex): bool
    {
        $negated = false;

        for ($index = $wordIndex - 1; $index >= max(0, $wordIndex - 3); $index--) {
            if (in_array($tokens[$index], self::CONTRAST_WORDS, true)) {
                break;
            }

            if (in_array($tokens[$index], self::NEGATIONS, true)) {
                $negated = ! $negated;
            }
        }

        return $negated;
    }
}
