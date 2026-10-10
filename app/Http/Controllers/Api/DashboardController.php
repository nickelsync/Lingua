<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExerciseAttempt;
use App\Models\LearningActivity;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Notification;
use App\Models\SpeakingAttempt;
use App\Models\UserAchievement;
use App\Models\WritingSubmission;
use App\Models\XpTransaction;
use App\Services\ProgressService;
use App\Services\SpacedRepetitionService;
use App\Services\StreakService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(
        private ProgressService $progress,
        private SpacedRepetitionService $srs,
        private StreakService $streaks,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $level = $user->currentLevel;

        $continueLesson = $this->continueLearning($user);

        $recentActivity = LearningActivity::where('user_id', $user->id)
            ->latest()->limit(8)->get(['type', 'subject', 'xp', 'local_date', 'created_at']);

        $graph = LearningActivity::where('user_id', $user->id)
            ->where('local_date', '>=', now()->subDays(83)->toDateString())
            ->select('local_date', DB::raw('SUM(xp) as xp'), DB::raw('COUNT(*) as count'))
            ->groupBy('local_date')->orderBy('local_date')->get();

        $achievements = UserAchievement::with('achievement')
            ->where('user_id', $user->id)->latest('earned_at')->limit(6)->get()
            ->map(fn ($ua) => [
                'name' => $ua->achievement->name,
                'icon' => $ua->achievement->icon,
                'earned_at' => $ua->earned_at->toDateString(),
            ]);

        return response()->json([
            'level' => $level?->only(['id', 'code', 'name']),
            'level_progress' => $level ? $this->progress->levelProgress($user, $level) : null,
            'xp_total' => $user->xp_total,
            'account_level' => $user->accountLevel(),
            'streak' => $this->streaks->effectiveStreak($user),
            'due_reviews' => $this->srs->dueCount($user->id),
            'continue' => $continueLesson,
            'skills' => $level ? $this->progress->skillProgress($user, $level) : [],
            'activity_graph' => $graph,
            'recent_activity' => $recentActivity,
            'achievements' => $achievements,
            'lessons_completed' => $user->lessonCompletions()->count(),
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        $accuracy = ExerciseAttempt::where('user_id', $user->id)->avg('score');

        $xpHistory = XpTransaction::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(amount) as xp'))
            ->groupBy('date')->orderBy('date')->get();

        $skillActivity = LearningActivity::where('user_id', $user->id)
            ->select('type', DB::raw('COUNT(*) as count'), DB::raw('SUM(xp) as xp'))
            ->groupBy('type')->get();

        $studyMinutes = LessonCompletion::where('user_id', $user->id)
            ->join('lessons', 'lessons.id', '=', 'lesson_completions.lesson_id')
            ->sum('lessons.estimated_minutes');

        return response()->json([
            'lessons_completed' => $user->lessonCompletions()->count(),
            'exercises_completed' => $user->exerciseAttempts()->count(),
            'average_accuracy' => $accuracy !== null ? (int) round($accuracy) : null,
            'words_saved' => $user->vocabulary()->count(),
            'words_reviewed' => (int) $user->vocabulary()->sum('review_count'),
            'speaking_attempts' => SpeakingAttempt::where('user_id', $user->id)->count(),
            'writing_submissions' => WritingSubmission::where('user_id', $user->id)->count(),
            'estimated_study_minutes' => (int) $studyMinutes,
            'streak_current' => $this->streaks->effectiveStreak($user),
            'streak_longest' => $user->streak_longest,
            'xp_history' => $xpHistory,
            'skill_activity' => $skillActivity,
            'skills_overall' => $this->progress->skillProgress($user),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $user = $request->user();

        $completions = LessonCompletion::with('lesson:id,title,slug')
            ->where('user_id', $user->id)->latest('first_completed_at')->limit(50)
            ->get(['lesson_id', 'first_completed_at', 'completion_count']);

        $attempts = ExerciseAttempt::with('exercise:id,title,lesson_id')
            ->where('user_id', $user->id)->latest()->limit(50)
            ->get(['id', 'exercise_id', 'score', 'correct_count', 'question_count', 'xp_awarded', 'created_at']);

        return response()->json([
            'lesson_completions' => $completions,
            'exercise_attempts' => $attempts,
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->latest()->limit(30)->get();

        return response()->json([
            'notifications' => $notifications,
            'unread' => $notifications->whereNull('read_at')->count(),
        ]);
    }

    public function markNotificationsRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'Marked as read.']);
    }

    private function continueLearning($user): ?array
    {
        $completed = LessonCompletion::where('user_id', $user->id)->pluck('lesson_id')->all();

        $lesson = null;
        $lastCompletion = LessonCompletion::where('user_id', $user->id)
            ->latest('updated_at')->first();

        if ($lastCompletion) {
            $lastLesson = Lesson::find($lastCompletion->lesson_id);
            if ($lastLesson) {
                $lesson = Lesson::where('module_id', $lastLesson->module_id)
                    ->where('is_published', true)
                    ->whereNotIn('id', $completed)
                    ->orderBy('sort_order')->first();
            }
        }

        if (! $lesson && $user->current_level_id) {
            $lesson = Lesson::where('is_published', true)
                ->whereNotIn('id', $completed)
                ->whereHas('module.course', fn ($q) => $q->where('cefr_level_id', $user->current_level_id)->where('is_published', true))
                ->orderBy('id')->first();
        }

        if (! $lesson) {
            return null;
        }

        $lesson->load('module.course');

        return [
            'lesson' => $lesson->only(['id', 'title', 'slug', 'estimated_minutes']),
            'module' => $lesson->module->only(['id', 'title']),
            'course' => $lesson->module->course->only(['id', 'title', 'skill']),
        ];
    }
}
