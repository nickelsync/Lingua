<?php

namespace App\Services;

use App\Models\CefrLevel;
use App\Models\ExamAttempt;
use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\LevelUnlock;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProgressService
{
    /**
     * Level progress percent: 70% content completion + 30% performance.
     */
    public function levelProgress(User $user, CefrLevel $level): array
    {
        $lessonIds = Lesson::where('is_published', true)
            ->whereHas('module.course', fn ($q) => $q->where('cefr_level_id', $level->id)->where('is_published', true))
            ->pluck('id');

        $totalLessons = $lessonIds->count();
        $completedLessons = $totalLessons === 0 ? 0
            : LessonCompletion::where('user_id', $user->id)->whereIn('lesson_id', $lessonIds)->count();

        $exerciseIds = Exercise::where('is_published', true)
            ->whereIn('lesson_id', $lessonIds)->pluck('id');

        $bestScores = ExerciseAttempt::where('user_id', $user->id)
            ->whereIn('exercise_id', $exerciseIds)
            ->select('exercise_id', DB::raw('MAX(score) as best'))
            ->groupBy('exercise_id')
            ->pluck('best');

        $contentPct = $totalLessons > 0 ? $completedLessons / $totalLessons : 0;
        $performancePct = $exerciseIds->count() > 0
            ? ($bestScores->sum() / 100) / $exerciseIds->count()
            : $contentPct;

        $percent = (int) round(($contentPct * 0.7 + $performancePct * 0.3) * 100);

        return [
            'percent' => min(100, $percent),
            'lessons_total' => $totalLessons,
            'lessons_completed' => $completedLessons,
            'content_percent' => (int) round($contentPct * 100),
        ];
    }

    public function contentComplete(User $user, CefrLevel $level): bool
    {
        return $this->levelProgress($user, $level)['content_percent'] >= 100;
    }

    /** Skill progress within the user's current level (and overall). */
    public function skillProgress(User $user, ?CefrLevel $level = null): array
    {
        $skills = ['grammar', 'vocabulary', 'listening', 'speaking', 'reading', 'writing', 'pronunciation', 'everyday'];
        $result = [];

        foreach ($skills as $skill) {
            $query = Lesson::where('is_published', true)
                ->whereHas('module.course', function ($q) use ($skill, $level) {
                    $q->where('skill', $skill)->where('is_published', true);
                    if ($level) {
                        $q->where('cefr_level_id', $level->id);
                    }
                });

            $total = (clone $query)->count();
            if ($total === 0) {
                continue;
            }
            $ids = $query->pluck('id');
            $done = LessonCompletion::where('user_id', $user->id)->whereIn('lesson_id', $ids)->count();
            $result[] = [
                'skill' => $skill,
                'total' => $total,
                'completed' => $done,
                'percent' => (int) round($done / $total * 100),
            ];
        }

        return $result;
    }

    public function unlockedLevelIds(User $user): array
    {
        return LevelUnlock::where('user_id', $user->id)->pluck('cefr_level_id')->all();
    }

    public function isLevelUnlocked(User $user, CefrLevel $level): bool
    {
        return LevelUnlock::where('user_id', $user->id)->where('cefr_level_id', $level->id)->exists();
    }

    /** Whether every prerequisite of a lesson is completed by the user. */
    public function prerequisitesMet(User $user, Lesson $lesson): bool
    {
        $prereqIds = $lesson->prerequisites()->pluck('lessons.id');
        if ($prereqIds->isEmpty()) {
            return true;
        }

        $completed = LessonCompletion::where('user_id', $user->id)
            ->whereIn('lesson_id', $prereqIds)->count();

        return $completed === $prereqIds->count();
    }

    /** Weak skills from a failed exam attempt: skills scoring under 60%. */
    public function weakSkills(ExamAttempt $attempt): array
    {
        $breakdown = $attempt->skill_breakdown ?? [];
        $weak = [];
        foreach ($breakdown as $skill => $stats) {
            $pct = $stats['total'] > 0 ? $stats['correct'] / $stats['total'] * 100 : 0;
            if ($pct < 60) {
                $weak[] = $skill;
            }
        }

        return $weak;
    }
}
