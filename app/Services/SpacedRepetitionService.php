<?php

namespace App\Services;

use App\Models\UserVocabulary;

/**
 * Simplified SM-2. Quality is binary-ish: again(0), hard(1), good(2), easy(3).
 */
class SpacedRepetitionService
{
    public function review(UserVocabulary $entry, int $quality): UserVocabulary
    {
        $quality = max(0, min(3, $quality));
        // Attributes may be null on models created without a DB refresh; apply schema defaults.
        $entry->ease = $entry->ease ?? 2.5;
        $entry->interval_days = $entry->interval_days ?? 0;
        $entry->review_count = $entry->review_count ?? 0;
        $entry->correct_count = $entry->correct_count ?? 0;
        $entry->incorrect_count = $entry->incorrect_count ?? 0;
        $entry->review_count++;

        if ($quality === 0) {
            $entry->incorrect_count++;
            $entry->interval_days = 0; // due again today
            $entry->ease = max(1.3, $entry->ease - 0.2);
            $entry->next_review_at = now()->addHours(4);
        } else {
            $entry->correct_count++;
            $entry->ease = match ($quality) {
                1 => max(1.3, $entry->ease - 0.15),
                2 => $entry->ease,
                default => min(3.0, $entry->ease + 0.1),
            };

            $entry->interval_days = match (true) {
                $entry->interval_days === 0 => 1,
                $entry->interval_days === 1 => $quality === 1 ? 2 : 3,
                default => (int) round($entry->interval_days * ($quality === 1 ? 1.2 : $entry->ease)),
            };
            $entry->interval_days = min($entry->interval_days, 365);
            $entry->next_review_at = now()->addDays($entry->interval_days);
        }

        $entry->last_reviewed_at = now();
        $entry->save();

        return $entry;
    }

    public function dueCount(int $userId): int
    {
        return UserVocabulary::where('user_id', $userId)
            ->where(function ($q) {
                $q->whereNull('next_review_at')->orWhere('next_review_at', '<=', now());
            })
            ->count();
    }
}
