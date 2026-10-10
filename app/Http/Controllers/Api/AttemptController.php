<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Services\AnswerChecker;
use App\Services\ProgressService;
use App\Services\XpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttemptController extends Controller
{
    public function __construct(
        private AnswerChecker $checker,
        private XpService $xp,
        private ProgressService $progress,
    ) {}

    /** Check a single answer immediately (normal exercises). */
    public function checkAnswer(Request $request, Question $question): JsonResponse
    {
        $data = $request->validate(['answer' => ['required']]);

        $exercise = $question->exercise;
        abort_if($exercise->kind !== 'practice' && $exercise->kind !== 'placement' && ! $request->user()->isAdmin(), 403, 'Exam answers cannot be checked live.');
        // Never reveal answers for exam-pool questions outside a graded submission.
        abort_if($exercise->kind === 'exam_pool', 403, 'Exam answers cannot be checked live.');

        $isCorrect = $this->checker->check($question, $data['answer']);

        return response()->json([
            'correct' => $isCorrect,
            'explanation' => $question->explanation,
            'explanation_id' => $question->explanation_id,
            'correct_answer' => $isCorrect ? null : $this->revealAnswer($question),
        ]);
    }

    /** Submit a full exercise attempt: server re-checks every answer. */
    public function submitExercise(Request $request, Exercise $exercise): JsonResponse
    {
        abort_if($exercise->kind !== 'practice', 403, 'Only practice exercises can be submitted here.');

        $data = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
        ]);

        $user = $request->user();
        $questions = $exercise->questions()->with('options')->get()->keyBy('id');

        $correct = 0;
        $evaluated = [];
        foreach ($data['answers'] as $qid => $answer) {
            $question = $questions->get((int) $qid);
            if (! $question) {
                continue;
            }
            $isCorrect = $this->checker->check($question, $answer);
            if ($isCorrect) {
                $correct++;
            }
            $evaluated[] = ['question' => $question, 'answer' => $answer, 'correct' => $isCorrect];
        }

        $count = count($evaluated);
        abort_if($count === 0, 422, 'No valid answers submitted.');
        $score = (int) round($correct / $count * 100);

        $attempt = DB::transaction(function () use ($user, $exercise, $score, $correct, $count, $evaluated) {
            $attempt = ExerciseAttempt::create([
                'user_id' => $user->id,
                'exercise_id' => $exercise->id,
                'score' => $score,
                'correct_count' => $correct,
                'question_count' => $count,
            ]);

            foreach ($evaluated as $e) {
                QuestionAttempt::create([
                    'user_id' => $user->id,
                    'question_id' => $e['question']->id,
                    'exercise_attempt_id' => $attempt->id,
                    'answer' => is_scalar($e['answer']) ? (string) $e['answer'] : json_encode($e['answer']),
                    'is_correct' => $e['correct'],
                ]);
            }

            return $attempt;
        });

        // XP scaled by score; anti-farming enforced by reference.
        $xpAmount = (int) round($exercise->xp_reward * $score / 100);
        $awarded = $this->xp->award($user, $xpAmount, 'exercise', 'exercise:'.$exercise->id, $exercise->title);
        $attempt->update(['xp_awarded' => $awarded]);

        return response()->json([
            'score' => $score,
            'correct' => $correct,
            'total' => $count,
            'xp_awarded' => $awarded,
            'best_score' => ExerciseAttempt::where('user_id', $user->id)->where('exercise_id', $exercise->id)->max('score'),
        ]);
    }

    /** Complete a lesson: requires every published lesson exercise attempted. */
    public function completeLesson(Request $request, Lesson $lesson): JsonResponse
    {
        $user = $request->user();
        abort_unless($lesson->is_published, 404);
        abort_unless($this->progress->prerequisitesMet($user, $lesson), 403, 'Prerequisites not met.');

        $exerciseIds = $lesson->exercises()->where('is_published', true)->pluck('id');
        if ($exerciseIds->isNotEmpty()) {
            $attempted = ExerciseAttempt::where('user_id', $user->id)
                ->whereIn('exercise_id', $exerciseIds)
                ->distinct('exercise_id')->count('exercise_id');

            if ($attempted < $exerciseIds->count()) {
                return response()->json([
                    'message' => 'Complete all exercises in this lesson first.',
                    'exercises_done' => $attempted,
                    'exercises_total' => $exerciseIds->count(),
                ], 422);
            }
        }

        $completion = LessonCompletion::firstOrNew([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
        ]);

        $isFirst = ! $completion->exists;
        if ($isFirst) {
            $completion->first_completed_at = now();
            $completion->completion_count = 1;
        } else {
            $completion->completion_count++;
        }
        $completion->save();

        $awarded = $this->xp->award($user, $lesson->xp_reward, 'lesson', 'lesson:'.$lesson->id, $lesson->title);

        return response()->json([
            'completed' => true,
            'first_time' => $isFirst,
            'xp_awarded' => $awarded,
        ]);
    }

    private function revealAnswer(Question $question): mixed
    {
        return match ($question->type) {
            'multiple_choice', 'listening', 'reading' => $question->options->firstWhere('is_correct', true)?->text,
            'true_false' => ($question->answer_config['correct'] ?? false) ? 'True' : 'False',
            'fill_blank', 'translation', 'typing' => ($question->answer_config['accepted'] ?? [null])[0],
            'rearrange' => implode(' ', $question->answer_config['order'] ?? []),
            'matching' => $question->answer_config['pairs'] ?? [],
            default => null,
        };
    }
}
