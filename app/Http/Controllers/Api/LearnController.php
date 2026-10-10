<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CefrLevel;
use App\Models\Course;
use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Services\ProgressService;
use App\Services\XpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearnController extends Controller
{
    public function __construct(
        private ProgressService $progress,
        private XpService $xp,
    ) {}

    /** All CEFR levels with unlock state and progress. */
    public function levels(Request $request): JsonResponse
    {
        $user = $request->user();
        $unlocked = $this->progress->unlockedLevelIds($user);

        $levels = CefrLevel::orderBy('sort_order')->get()->map(function ($level) use ($user, $unlocked) {
            $prog = $this->progress->levelProgress($user, $level);

            return [
                'id' => $level->id,
                'code' => $level->code,
                'name' => $level->name,
                'description' => $level->description,
                'sort_order' => $level->sort_order,
                'has_exam' => $level->has_exam,
                'unlocked' => in_array($level->id, $unlocked, true),
                'progress' => $prog,
            ];
        });

        return response()->json(['levels' => $levels]);
    }

    /** Courses of a level with per-course progress. */
    public function level(Request $request, CefrLevel $level): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->progress->isLevelUnlocked($user, $level) || $user->isAdmin(), 403, 'This level is locked. Pass the previous final exam to unlock it.');

        $courses = $level->courses()->where('is_published', true)->with(['modules' => function ($q) {
            $q->where('is_published', true);
        }, 'modules.lessons' => function ($q) {
            $q->where('is_published', true)->select('id', 'module_id', 'title', 'slug', 'sort_order', 'estimated_minutes', 'xp_reward');
        }])->get();

        $completedIds = LessonCompletion::where('user_id', $user->id)->pluck('lesson_id')->all();

        $payload = $courses->map(function ($course) use ($completedIds) {
            $lessonTotal = 0;
            $lessonDone = 0;
            $modules = $course->modules->map(function ($module) use ($completedIds, &$lessonTotal, &$lessonDone) {
                $lessons = $module->lessons->map(function ($lesson) use ($completedIds, &$lessonTotal, &$lessonDone) {
                    $lessonTotal++;
                    $done = in_array($lesson->id, $completedIds, true);
                    if ($done) {
                        $lessonDone++;
                    }

                    return [
                        'id' => $lesson->id,
                        'title' => $lesson->title,
                        'slug' => $lesson->slug,
                        'estimated_minutes' => $lesson->estimated_minutes,
                        'xp_reward' => $lesson->xp_reward,
                        'completed' => $done,
                    ];
                });

                return [
                    'id' => $module->id,
                    'title' => $module->title,
                    'description' => $module->description,
                    'lessons' => $lessons,
                ];
            });

            return [
                'id' => $course->id,
                'skill' => $course->skill,
                'title' => $course->title,
                'slug' => $course->slug,
                'description' => $course->description,
                'modules' => $modules,
                'lessons_total' => $lessonTotal,
                'lessons_completed' => $lessonDone,
            ];
        });

        return response()->json([
            'level' => $level->only(['id', 'code', 'name', 'description', 'has_exam', 'exam_pass_score', 'exam_time_limit_minutes', 'exam_question_count']),
            'progress' => $this->progress->levelProgress($user, $level),
            'content_complete' => $this->progress->contentComplete($user, $level),
            'courses' => $payload,
        ]);
    }

    /** Full lesson content, gated by prerequisites and level unlock. */
    public function lesson(Request $request, Lesson $lesson): JsonResponse
    {
        $user = $request->user();
        abort_unless($lesson->is_published || $user->isAdmin(), 404);

        $level = $lesson->module->course->level;
        abort_unless($this->progress->isLevelUnlocked($user, $level) || $user->isAdmin(), 403, 'This level is locked.');

        if (! $this->progress->prerequisitesMet($user, $lesson) && ! $user->isAdmin()) {
            $missing = $lesson->prerequisites()
                ->whereNotIn('lessons.id', LessonCompletion::where('user_id', $user->id)->pluck('lesson_id'))
                ->get(['lessons.id', 'lessons.title', 'lessons.slug']);

            return response()->json([
                'message' => 'Complete the prerequisite lessons first.',
                'missing_prerequisites' => $missing,
            ], 403);
        }

        $lesson->load(['sections', 'exercises' => fn ($q) => $q->where('is_published', true), 'exercises.questions.options']);

        $completion = LessonCompletion::where('user_id', $user->id)->where('lesson_id', $lesson->id)->first();

        $bestScores = ExerciseAttempt::where('user_id', $user->id)
            ->whereIn('exercise_id', $lesson->exercises->pluck('id'))
            ->selectRaw('exercise_id, MAX(score) as best, COUNT(*) as attempts')
            ->groupBy('exercise_id')->get()->keyBy('exercise_id');

        return response()->json([
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'slug' => $lesson->slug,
                'objective' => $lesson->objective,
                'xp_reward' => $lesson->xp_reward,
                'estimated_minutes' => $lesson->estimated_minutes,
                'module' => ['id' => $lesson->module->id, 'title' => $lesson->module->title],
                'course' => ['id' => $lesson->module->course->id, 'title' => $lesson->module->course->title, 'skill' => $lesson->module->course->skill],
                'level' => $level->only(['id', 'code', 'name']),
                'completed' => $completion !== null,
                'sections' => $lesson->sections->map(fn ($s) => [
                    'id' => $s->id,
                    'type' => $s->type,
                    'title' => $s->title,
                    'body' => $s->body,
                    'body_id' => $s->body_id,
                    'meta' => $s->meta,
                ]),
                'exercises' => $lesson->exercises->map(function ($ex) use ($bestScores) {
                    $stats = $bestScores->get($ex->id);

                    return [
                        'id' => $ex->id,
                        'title' => $ex->title,
                        'xp_reward' => $ex->xp_reward,
                        'question_total' => $ex->questions->count(),
                        'best_score' => $stats?->best,
                        'attempts' => $stats?->attempts ?? 0,
                    ];
                }),
            ],
            'next_lesson' => $this->nextLesson($lesson),
        ]);
    }

    /** Randomized questions for an exercise (correct answers stripped). */
    public function exercise(Request $request, Exercise $exercise): JsonResponse
    {
        $user = $request->user();
        abort_unless($exercise->is_published || $user->isAdmin(), 404);
        abort_if($exercise->kind !== 'practice', 403, 'This exercise is not directly playable.');

        if ($exercise->lesson) {
            $level = $exercise->lesson->module->course->level;
            abort_unless($this->progress->isLevelUnlocked($user, $level) || $user->isAdmin(), 403, 'This level is locked.');
        }

        $questions = $exercise->questions()->with('options')->get()->shuffle();
        if ($exercise->question_count > 0) {
            $questions = $questions->take($exercise->question_count);
        }

        return response()->json([
            'exercise' => [
                'id' => $exercise->id,
                'title' => $exercise->title,
                'xp_reward' => $exercise->xp_reward,
                'lesson_id' => $exercise->lesson_id,
            ],
            'questions' => $questions->values()->map(fn ($q) => $this->publicQuestion($q)),
        ]);
    }

    public static function publicQuestionPayload($q): array
    {
        $payload = [
            'id' => $q->id,
            'type' => $q->type,
            'skill' => $q->skill,
            'prompt' => $q->prompt,
            'passage' => $q->passage,
            'audio_text' => $q->audio_text,
            'audio_path' => $q->audio_path ? '/storage/'.$q->audio_path : null,
            'difficulty' => $q->difficulty,
        ];

        if (in_array($q->type, ['multiple_choice', 'listening', 'reading'], true)) {
            $payload['options'] = $q->options->shuffle()->values()->map(fn ($o) => ['id' => $o->id, 'text' => $o->text]);
        }
        if ($q->type === 'rearrange') {
            $tokens = $q->answer_config['order'] ?? [];
            $shuffled = $tokens;
            // guarantee visible shuffle for 2+ tokens
            for ($i = 0; $i < 5 && $shuffled === $tokens && count($tokens) > 1; $i++) {
                shuffle($shuffled);
            }
            $payload['tokens'] = $shuffled;
        }
        if ($q->type === 'matching') {
            $pairs = $q->answer_config['pairs'] ?? [];
            $payload['left'] = array_map(fn ($p) => $p['left'], $pairs);
            $rights = array_map(fn ($p) => $p['right'], $pairs);
            shuffle($rights);
            $payload['right'] = $rights;
        }
        if ($q->type === 'speaking') {
            $payload['target'] = $q->answer_config['target'] ?? $q->prompt;
        }
        if ($q->type === 'true_false') {
            $payload['options'] = [['id' => 'true', 'text' => 'True'], ['id' => 'false', 'text' => 'False']];
        }

        return $payload;
    }

    private function publicQuestion($q): array
    {
        return self::publicQuestionPayload($q);
    }

    private function nextLesson(Lesson $lesson): ?array
    {
        $next = Lesson::where('module_id', $lesson->module_id)
            ->where('is_published', true)
            ->where('sort_order', '>', $lesson->sort_order)
            ->orderBy('sort_order')->first();

        if (! $next) {
            $module = $lesson->module;
            $nextModule = $module->course->modules()
                ->where('is_published', true)
                ->where('sort_order', '>', $module->sort_order)
                ->orderBy('sort_order')->first();
            $next = $nextModule?->lessons()->where('is_published', true)->orderBy('sort_order')->first();
        }

        return $next?->only(['id', 'title', 'slug']);
    }
}
