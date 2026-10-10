<?php

namespace App\Services;

use App\Models\User;

class StreakService
{
    /** Update streak based on the user's local calendar date. */
    public function recordActivity(User $user): void
    {
        $today = now($user->timezone ?: 'UTC')->toDateString();
        $last = $user->last_activity_date?->toDateString();

        if ($last === $today) {
            return;
        }

        $yesterday = now($user->timezone ?: 'UTC')->subDay()->toDateString();
        $user->streak_current = $last === $yesterday ? $user->streak_current + 1 : 1;
        $user->streak_longest = max($user->streak_longest, $user->streak_current);
        $user->last_activity_date = $today;
        $user->save();
    }

    /** Streak read with decay: if the user missed a full local day, streak resets. */
    public function effectiveStreak(User $user): int
    {
        $last = $user->last_activity_date?->toDateString();
        if ($last === null) {
            return 0;
        }

        $tz = $user->timezone ?: 'UTC';
        $today = now($tz)->toDateString();
        $yesterday = now($tz)->subDay()->toDateString();

        return in_array($last, [$today, $yesterday], true) ? $user->streak_current : 0;
    }
}
