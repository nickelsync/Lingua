<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\ExamAttempt;
use App\Models\ExerciseAttempt;
use App\Models\LearningActivity;
use App\Models\LessonCompletion;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserVocabulary;
use App\Models\Vocabulary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    // ---- Overview / analytics ----
    public function overview(): JsonResponse
    {
        $activeSince = now()->subDays(7);

        $cefrDistribution = User::where('role', 'user')
            ->join('cefr_levels', 'cefr_levels.id', '=', 'users.current_level_id')
            ->select('cefr_levels.code', DB::raw('COUNT(*) as count'))
            ->groupBy('cefr_levels.code', 'cefr_levels.sort_order')
            ->orderBy('cefr_levels.sort_order')->get();

        $hardestLessons = ExerciseAttempt::join('exercises', 'exercises.id', '=', 'exercise_attempts.exercise_id')
            ->join('lessons', 'lessons.id', '=', 'exercises.lesson_id')
            ->select('lessons.id', 'lessons.title', DB::raw('AVG(exercise_attempts.score) as avg_score'), DB::raw('COUNT(*) as attempts'))
            ->groupBy('lessons.id', 'lessons.title')
            ->having(DB::raw('COUNT(*)'), '>=', 3)
            ->orderBy('avg_score')->limit(10)->get();

        $failedQuestions = QuestionAttempt::join('questions', 'questions.id', '=', 'question_attempts.question_id')
            ->select('questions.id', 'questions.prompt', DB::raw('COUNT(*) as attempts'),
                DB::raw('SUM(CASE WHEN is_correct THEN 0 ELSE 1 END) as failures'))
            ->groupBy('questions.id', 'questions.prompt')
            ->having(DB::raw('COUNT(*)'), '>=', 3)
            ->orderByRaw('(SUM(CASE WHEN is_correct THEN 0 ELSE 1 END) * 1.0) / COUNT(*) DESC')
            ->limit(10)->get();

        $activityByDay = LearningActivity::where('created_at', '>=', now()->subDays(30))
            ->select(DB::raw('local_date'), DB::raw('COUNT(DISTINCT user_id) as users'), DB::raw('COUNT(*) as activities'))
            ->groupBy('local_date')->orderBy('local_date')->get();

        return response()->json([
            'total_users' => User::where('role', 'user')->count(),
            'active_users_7d' => LearningActivity::where('created_at', '>=', $activeSince)->distinct('user_id')->count('user_id'),
            'lessons_completed' => LessonCompletion::count(),
            'average_quiz_score' => (int) round(ExerciseAttempt::avg('score') ?? 0),
            'exams_taken' => ExamAttempt::whereNotNull('submitted_at')->count(),
            'exam_pass_rate' => $this->examPassRate(),
            'words_saved' => UserVocabulary::count(),
            'vocab_reviews' => (int) UserVocabulary::sum('review_count'),
            'cefr_distribution' => $cefrDistribution,
            'hardest_lessons' => $hardestLessons,
            'most_failed_questions' => $failedQuestions,
            'activity_by_day' => $activityByDay,
        ]);
    }

    private function examPassRate(): ?int
    {
        $total = ExamAttempt::where('kind', 'final')->whereNotNull('submitted_at')->count();
        if ($total === 0) {
            return null;
        }
        $passed = ExamAttempt::where('kind', 'final')->where('passed', true)->count();

        return (int) round($passed / $total * 100);
    }

    // ---- Users ----
    public function users(Request $request): JsonResponse
    {
        $query = User::with('currentLevel:id,code')->withCount('lessonCompletions');

        if ($search = $request->query('q')) {
            $query->where(fn ($q) => $q->whereLike('username', "%{$search}%", caseSensitive: false)->orWhereLike('display_name', "%{$search}%", caseSensitive: false));
        }
        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }
        if ($request->query('suspended') === '1') {
            $query->whereNotNull('suspended_at');
        }

        return response()->json(['users' => $query->orderBy('id', 'desc')->paginate(25)]);
    }

    public function showUser(User $user): JsonResponse
    {
        $recentActivity = LearningActivity::where('user_id', $user->id)->latest()->limit(20)->get();
        $examHistory = ExamAttempt::with('level:id,code')->where('user_id', $user->id)
            ->whereNotNull('submitted_at')->latest('submitted_at')->limit(10)
            ->get(['id', 'cefr_level_id', 'kind', 'score', 'passed', 'submitted_at']);

        return response()->json([
            'user' => $user->load('currentLevel:id,code,name'),
            'stats' => [
                'lessons_completed' => $user->lessonCompletions()->count(),
                'exercises' => $user->exerciseAttempts()->count(),
                'avg_score' => (int) round($user->exerciseAttempts()->avg('score') ?? 0),
                'words_saved' => $user->vocabulary()->count(),
                'xp_total' => $user->xp_total,
                'streak' => $user->streak_current,
            ],
            'recent_activity' => $recentActivity,
            'exam_history' => $examHistory,
        ]);
    }

    public function suspendUser(Request $request, User $user): JsonResponse
    {
        abort_if($user->isAdmin(), 422, 'Admins cannot be suspended.');
        $user->forceFill(['suspended_at' => $user->suspended_at ? null : now()])->save();

        return response()->json(['suspended' => $user->suspended_at !== null]);
    }

    /**
     * Issue a fresh recovery code for a locked-out user (no email exists).
     * Shown once to the admin, stored only as a hash.
     */
    public function regenerateRecoveryCode(User $user): JsonResponse
    {
        $code = Str::upper(Str::random(4).'-'.Str::random(4).'-'.Str::random(4));
        $user->forceFill(['recovery_code' => Hash::make($code)])->save();

        return response()->json([
            'message' => 'New recovery code issued. Share it with the user securely; it will not be shown again.',
            'recovery_code' => $code,
        ]);
    }

    public function updateUserRole(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['role' => ['required', Rule::in(['user', 'admin'])]]);
        abort_if($user->id === $request->user()->id, 422, 'You cannot change your own role.');
        $user->forceFill(['role' => $data['role']])->save();

        return response()->json(['user' => $user]);
    }

    // ---- Vocabulary ----
    public function vocabulary(Request $request): JsonResponse
    {
        $query = Vocabulary::with('level:id,code');
        if ($search = $request->query('q')) {
            $query->whereLike('word', "%{$search}%", caseSensitive: false);
        }
        if ($levelId = $request->query('level_id')) {
            $query->where('cefr_level_id', $levelId);
        }

        return response()->json(['vocabulary' => $query->orderBy('word')->paginate(30)]);
    }

    public function storeVocabulary(Request $request): JsonResponse
    {
        $data = $this->validateVocabulary($request);

        return response()->json(['word' => Vocabulary::create($data)], 201);
    }

    public function updateVocabulary(Request $request, Vocabulary $vocabulary): JsonResponse
    {
        $data = $this->validateVocabulary($request);
        $vocabulary->update($data);

        return response()->json(['word' => $vocabulary]);
    }

    public function deleteVocabulary(Vocabulary $vocabulary): JsonResponse
    {
        $vocabulary->delete();

        return response()->json(['message' => 'Word deleted.']);
    }

    private function validateVocabulary(Request $request): array
    {
        return $request->validate([
            'word' => ['required', 'string', 'max:80'],
            'part_of_speech' => ['nullable', 'string', 'max:20'],
            'ipa' => ['nullable', 'string', 'max:100'],
            'definition' => ['required', 'string'],
            'meaning_id' => ['nullable', 'string'],
            'example' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:40'],
            'cefr_level_id' => ['nullable', 'exists:cefr_levels,id'],
            'difficulty' => ['required', Rule::in(['easy', 'medium', 'hard'])],
            'usage_note' => ['nullable', 'string', 'max:255'],
        ]);
    }

    // ---- Achievements ----
    public function achievements(): JsonResponse
    {
        return response()->json(['achievements' => Achievement::orderBy('metric')->orderBy('threshold')->get()]);
    }

    public function storeAchievement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:achievements,code'],
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string'],
            'icon' => ['required', 'string', 'max:30'],
            'metric' => ['required', Rule::in(['lessons', 'exercises', 'words', 'reviews', 'streak', 'speaking', 'writing', 'xp', 'levels'])],
            'threshold' => ['required', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);

        return response()->json(['achievement' => Achievement::create($data)], 201);
    }

    public function updateAchievement(Request $request, Achievement $achievement): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:80'],
            'description' => ['sometimes', 'nullable', 'string'],
            'icon' => ['sometimes', 'string', 'max:30'],
            'metric' => ['sometimes', Rule::in(['lessons', 'exercises', 'words', 'reviews', 'streak', 'speaking', 'writing', 'xp', 'levels'])],
            'threshold' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $achievement->update($data);

        return response()->json(['achievement' => $achievement]);
    }

    public function deleteAchievement(Achievement $achievement): JsonResponse
    {
        $achievement->delete();

        return response()->json(['message' => 'Achievement deleted.']);
    }

    // ---- Audio upload for questions/vocabulary ----
    public function uploadAudio(Request $request): JsonResponse
    {
        $request->validate([
            'audio' => ['required', 'file', 'mimes:mp3,wav,ogg,m4a', 'max:5120'],
            'target_type' => ['required', Rule::in(['question', 'vocabulary'])],
            'target_id' => ['required', 'integer'],
        ]);

        $path = $request->file('audio')->store('audio', 'public');

        if ($request->input('target_type') === 'question') {
            $target = Question::findOrFail($request->integer('target_id'));
        } else {
            $target = Vocabulary::findOrFail($request->integer('target_id'));
        }
        $target->update(['audio_path' => $path]);

        return response()->json(['audio_path' => '/storage/'.$path], 201);
    }

    // ---- Settings ----
    private const SETTING_DEFAULTS = [
        'xp_lesson_default' => '20',
        'xp_exercise_default' => '10',
        'xp_vocab_review' => '2',
        'xp_speaking' => '5',
        'xp_writing' => '15',
        'repeat_xp_percent' => '10',
    ];

    public function settings(): JsonResponse
    {
        $stored = Setting::pluck('value', 'key')->all();

        return response()->json(['settings' => array_merge(self::SETTING_DEFAULTS, $stored)]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($data['settings'] as $key => $value) {
            if (array_key_exists($key, self::SETTING_DEFAULTS) && $value !== null && $value !== '') {
                Setting::set($key, $value);
            }
        }

        $stored = Setting::pluck('value', 'key')->all();

        return response()->json(['settings' => array_merge(self::SETTING_DEFAULTS, $stored)]);
    }
}
