<?php

namespace App\Services;

use App\Models\Question;

class AnswerChecker
{
    /**
     * Evaluate a raw answer against a question. Returns [bool correct, mixed normalized].
     */
    public function check(Question $question, mixed $answer): bool
    {
        return match ($question->type) {
            'multiple_choice', 'listening', 'reading' => $this->checkOption($question, $answer),
            'true_false' => $this->checkTrueFalse($question, $answer),
            'fill_blank', 'translation', 'typing' => $this->checkText($question, $answer),
            'rearrange' => $this->checkRearrange($question, $answer),
            'matching' => $this->checkMatching($question, $answer),
            'speaking' => $this->checkSpeaking($question, $answer),
            default => false,
        };
    }

    private function checkOption(Question $question, mixed $answer): bool
    {
        $optionId = (int) $answer;

        return $question->options->firstWhere('id', $optionId)?->is_correct ?? false;
    }

    private function checkTrueFalse(Question $question, mixed $answer): bool
    {
        $expected = (bool) ($question->answer_config['correct'] ?? false);

        return filter_var($answer, FILTER_VALIDATE_BOOLEAN) === $expected;
    }

    private function checkText(Question $question, mixed $answer): bool
    {
        $accepted = $question->answer_config['accepted'] ?? [];
        $given = $this->normalize((string) $answer);

        foreach ((array) $accepted as $candidate) {
            if ($this->normalize($candidate) === $given) {
                return true;
            }
        }

        return false;
    }

    private function checkRearrange(Question $question, mixed $answer): bool
    {
        $correct = $question->answer_config['order'] ?? [];
        $given = is_array($answer) ? $answer : preg_split('/\s+/', trim((string) $answer));

        $normalizedCorrect = array_map(fn ($t) => $this->normalize($t), $correct);
        $normalizedGiven = array_map(fn ($t) => $this->normalize((string) $t), $given);

        return $normalizedCorrect === $normalizedGiven;
    }

    private function checkMatching(Question $question, mixed $answer): bool
    {
        $pairs = $question->answer_config['pairs'] ?? [];
        if (! is_array($answer) || count($pairs) === 0) {
            return false;
        }

        // answer: {left => right}
        foreach ($pairs as $pair) {
            $left = $pair['left'] ?? '';
            $right = $pair['right'] ?? '';
            $givenRight = $answer[$left] ?? null;
            if ($givenRight === null || $this->normalize($givenRight) !== $this->normalize($right)) {
                return false;
            }
        }

        return true;
    }

    private function checkSpeaking(Question $question, mixed $answer): bool
    {
        // For speaking, answer is a transcript; correct when word accuracy >= 70%.
        $target = $question->answer_config['target'] ?? $question->prompt;
        $result = app(SpeakingScorer::class)->score($target, (string) $answer);

        return $result['accuracy'] >= 70;
    }

    public function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[.,!?;:"\'’]/u', '', $text);

        return preg_replace('/\s+/', ' ', $text);
    }
}
