<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\Notification;
use App\Models\SpeakingAttempt;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\WritingSubmission;

class AchievementService
{
    public function checkAll(User $user): array
    {
        $earnedIds = UserAchievement::where('user_id', $user->id)->pluck('achievement_id')->all();
        $candidates = Achievement::where('is_active', true)->whereNotIn('id', $earnedIds)->get();
        $newlyEarned = [];

        foreach ($candidates as $achievement) {
            if ($this->metricValue($user, $achievement->metric) >= $achievement->threshold) {
                UserAchievement::create([
                    'user_id' => $user->id,
                    'achievement_id' => $achievement->id,
                    'earned_at' => now(),
                ]);
                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'achievement',
                    'title' => 'Achievement unlocked: '.$achievement->name,
                    'body' => $achievement->description,
                    'link' => '/app/progress',
                ]);
                $newlyEarned[] = $achievement;
            }
        }

        return $newlyEarned;
    }

    private function metricValue(User $user, string $metric): int
    {
        return match ($metric) {
            'lessons' => $user->lessonCompletions()->count(),
            'exercises' => $user->exerciseAttempts()->count(),
            'words' => $user->vocabulary()->count(),
            'reviews' => (int) $user->vocabulary()->sum('review_count'),
            'streak' => $user->streak_current,
            'speaking' => $user->hasMany(SpeakingAttempt::class)->count(),
            'writing' => $user->hasMany(WritingSubmission::class)->count(),
            'xp' => $user->xp_total,
            'levels' => $user->levelUnlocks()->where('source', 'exam')->count(),
            default => 0,
        };
    }
}
