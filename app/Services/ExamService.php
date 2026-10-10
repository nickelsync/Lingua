<?php

namespace App\Services;

use App\Models\CefrLevel;
use App\Models\ExamAttempt;
use App\Models\LevelUnlock;
use App\Models\Notification;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\User;

class ExamService
{
    public function __construct(
        private AnswerChecker $checker,
        private XpService $xp,
    ) {}

    /** Start a final exam: freeze a random question selection and a server-side deadline. */
    public function startFinalExam(User $user, CefrLevel $level): ExamAttempt
    {
        $active = ExamAttempt::where('user_id', $user->id)
            ->where('cefr_level_id', $level->id)
            ->where('kind', 'final')
            ->whereNull('submitted_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($active) {
            return $active;
        }

        $questionIds = Question::whereHas('exercise', function ($q) use ($level) {
            $q->where('kind', 'exam_pool')->where('cefr_level_id', $level->id)->where('is_published', true);
        })
            ->whereNotIn('type', ['speaking'])
            ->inRandomOrder()
            ->limit($level->exam_question_count)
            ->pluck('id')
            ->all();

        abort_if(count($questionIds) === 0, 422, 'No exam questions are available for this level yet.');

        return ExamAttempt::create([
            'user_id' => $user->id,
            'cefr_level_id' => $level->id,
            'kind' => 'final',
            'question_ids' => $questionIds,
            'answers' => [],
            'started_at' => now(),
            'expires_at' => now()->addMinutes($level->exam_time_limit_minutes),
        ]);
    }

    /** Grade and finalize. Server-side timing: late submissions still grade but only saved answers count. */
    public function submit(ExamAttempt $attempt, array $answers): ExamAttempt
    {
        abort_if($attempt->submitted_at !== null, 422, 'This exam was already submitted.');

        // Reject answers arriving well past the deadline (grace of 30s for network).
        $withinTime = $attempt->expires_at === null || now()->lte($attempt->expires_at->addSeconds(30));

        $questions = Question::with('options')->whereIn('id', $attempt->question_ids)->get()->keyBy('id');
        $correct = 0;
        $breakdown = [];
        $stored = [];

        foreach ($attempt->question_ids as $qid) {
            $question = $questions->get($qid);
            if (! $question) {
                continue;
            }
            $answer = $withinTime ? ($answers[$qid] ?? $answers[(string) $qid] ?? null) : null;
            $isCorrect = $answer !== null && $this->checker->check($question, $answer);
            if ($isCorrect) {
                $correct++;
            }

            $skill = $question->skill ?: 'general';
            $breakdown[$skill] ??= ['correct' => 0, 'total' => 0];
            $breakdown[$skill]['total']++;
            if ($isCorrect) {
                $breakdown[$skill]['correct']++;
            }

            $stored[$qid] = is_scalar($answer) ? $answer : json_encode($answer);

            QuestionAttempt::create([
                'user_id' => $attempt->user_id,
                'question_id' => $qid,
                'exam_attempt_id' => $attempt->id,
                'answer' => is_scalar($answer) ? (string) $answer : json_encode($answer),
                'is_correct' => $isCorrect,
            ]);
        }

        $total = count($attempt->question_ids);
        $score = $total > 0 ? (int) round($correct / $total * 100) : 0;
        $passed = $score >= $attempt->level->exam_pass_score;

        $attempt->fill([
            'answers' => $stored,
            'submitted_at' => now(),
            'score' => $score,
            'passed' => $passed,
            'skill_breakdown' => $breakdown,
        ])->save();

        $user = User::find($attempt->user_id);
        $this->xp->award($user, $passed ? 100 : 20, 'exam', 'exam:'.$attempt->cefr_level_id.':'.($passed ? 'pass' : 'fail:'.$attempt->id), $attempt->level->code.' final exam');

        if ($passed) {
            $this->unlockNextLevel($user, $attempt->level);
        }

        return $attempt->refresh();
    }

    public function unlockNextLevel(User $user, CefrLevel $passedLevel): ?CefrLevel
    {
        $next = CefrLevel::where('sort_order', '>', $passedLevel->sort_order)
            ->orderBy('sort_order')
            ->first();

        if (! $next) {
            return null;
        }

        $created = LevelUnlock::firstOrCreate(
            ['user_id' => $user->id, 'cefr_level_id' => $next->id],
            ['source' => 'exam', 'unlocked_at' => now()],
        );

        if ($created->wasRecentlyCreated) {
            $user->forceFill(['current_level_id' => $next->id])->save();
            Notification::create([
                'user_id' => $user->id,
                'type' => 'level',
                'title' => $next->code === 'mastery'
                    ? 'English Mastery achieved!'
                    : $next->code.' unlocked',
                'body' => $next->code === 'mastery'
                    ? 'You have completed the full CEFR journey. Congratulations!'
                    : 'You passed the '.$passedLevel->code.' final exam. '.$next->code.' is now available.',
                'link' => '/app/learn',
            ]);
        }

        return $next;
    }
}
