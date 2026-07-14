<?php

namespace App\Services;

use App\Models\Feedback;

class SentimentClassifier
{
    private const BOUNDARY = '__boundary__';

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

        $config = config('sentiment');
        $tokens = $this->tokens($comment);
        $positiveTerms = $this->terms($config, 'positive');
        $negativeTerms = $this->terms($config, 'negative');
        $phrases = $this->phrases($config);
        $negations = $config['negations'] ?? [];
        $contrastWords = $config['contrast_words'] ?? [];
        $lexicalScore = 0;
        $matchedWords = 0;

        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];

            if ($this->isBoundary($token, $contrastWords)) {
                continue;
            }

            $match = $this->phraseAt($tokens, $index, $phrases);
            $polarity = $match['polarity'] ?? match (true) {
                in_array($token, $positiveTerms, true) => 1,
                in_array($token, $negativeTerms, true) => -1,
                default => 0,
            };

            if ($polarity === 0) {
                continue;
            }

            $matchedWords++;
            $lexicalScore += $this->isNegated($tokens, $index, $negations, $contrastWords) ? -$polarity : $polarity;
            $index += ($match['length'] ?? 1) - 1;
        }

        $label = match (true) {
            $matchedWords === 0 => $ratingLabel,
            $lexicalScore < 0 => Feedback::SENTIMENT_NEGATIVE,
            $ratingLabel === Feedback::SENTIMENT_NEGATIVE => Feedback::SENTIMENT_NEGATIVE,
            $lexicalScore > 0 => Feedback::SENTIMENT_POSITIVE,
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
        $comment = str_replace(['’', '‘', '`'], "'", mb_strtolower((string) $comment));

        preg_match_all("/[\\p{L}\\p{N}']+|[.!?;,:]+/u", $comment, $matches);

        return array_map(
            fn (string $token): string => preg_match('/^[.!?;,:]+$/u', $token) === 1 ? self::BOUNDARY : $token,
            $matches[0] ?? [],
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, string>
     */
    private function terms(array $config, string $polarity): array
    {
        $groups = array_values($config['terms'][$polarity] ?? []);

        return $groups === [] ? [] : array_values(array_unique(array_merge(...$groups)));
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, array{tokens: array<int, string>, polarity: int}>
     */
    private function phrases(array $config): array
    {
        $phrases = [];

        foreach (['positive' => 1, 'negative' => -1] as $polarity => $value) {
            $groups = array_values($config['phrases'][$polarity] ?? []);

            foreach ($groups === [] ? [] : array_merge(...$groups) as $phrase) {
                $phrases[] = ['tokens' => explode(' ', $phrase), 'polarity' => $value];
            }
        }

        usort($phrases, fn (array $left, array $right): int => count($right['tokens']) <=> count($left['tokens']));

        return $phrases;
    }

    /**
     * @param  array<int, string>  $tokens
     * @param  array<int, array{tokens: array<int, string>, polarity: int}>  $phrases
     * @return array{length: int, polarity: int}|null
     */
    private function phraseAt(array $tokens, int $index, array $phrases): ?array
    {
        foreach ($phrases as $phrase) {
            $length = count($phrase['tokens']);

            if (array_slice($tokens, $index, $length) === $phrase['tokens']) {
                return ['length' => $length, 'polarity' => $phrase['polarity']];
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function isNegated(array $tokens, int $wordIndex, array $negations, array $contrastWords): bool
    {
        $negated = false;

        for ($index = $wordIndex - 1; $index >= max(0, $wordIndex - 4); $index--) {
            if ($this->isBoundary($tokens[$index], $contrastWords)) {
                break;
            }

            if (in_array($tokens[$index], $negations, true)) {
                $negated = ! $negated;
            }
        }

        return $negated;
    }

    /**
     * @param  array<int, string>  $contrastWords
     */
    private function isBoundary(string $token, array $contrastWords): bool
    {
        return $token === self::BOUNDARY || in_array($token, $contrastWords, true);
    }
}
