<?php

namespace App\Services;

use App\Models\CefrLevel;
use App\Models\ExamAttempt;
use App\Models\LevelUnlock;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\User;

class PlacementService
{
    public function __construct(private AnswerChecker $checker) {}

    /**
     * Start a placement test: 50 MCQ-style + 10 speaking questions from the
     * placement pool, mixed across CEFR difficulty.
     */
    public function start(User $user): ExamAttempt
    {
        $beginner = CefrLevel::where('code', 'beginner')->firstOrFail();

        $active = ExamAttempt::where('user_id', $user->id)
            ->where('kind', 'placement')
            ->whereNull('submitted_at')
            ->first();

        if ($active) {
            return $active;
        }

        $written = Question::whereHas('exercise', fn ($q) => $q->where('kind', 'placement')->where('is_published', true))
            ->where('type', '!=', 'speaking')
            ->inRandomOrder()->limit(50)->pluck('id');

        $speaking = Question::whereHas('exercise', fn ($q) => $q->where('kind', 'placement')->where('is_published', true))
            ->where('type', 'speaking')
            ->inRandomOrder()->limit(10)->pluck('id');

        $ids = $written->merge($speaking)->all();
        abort_if(count($ids) === 0, 422, 'Placement questions are not available.');

        return ExamAttempt::create([
            'user_id' => $user->id,
            'cefr_level_id' => $beginner->id,
            'kind' => 'placement',
            'question_ids' => $ids,
            'answers' => [],
            'started_at' => now(),
            'expires_at' => null, // untimed
        ]);
    }

    /**
     * Grade placement: weighted by question difficulty band, mapped to CEFR.
     * Speaking answers are transcripts scored by word accuracy.
     */
    public function submit(ExamAttempt $attempt, array $answers): array
    {
        abort_if($attempt->submitted_at !== null, 422, 'This placement test was already submitted.');

        $questions = Question::with(['options', 'exercise.level'])
            ->whereIn('id', $attempt->question_ids)->get()->keyBy('id');

        $correct = 0;
        $total = 0;
        $weighted = 0.0;
        $weightTotal = 0.0;
        $breakdown = [];
        $stored = [];

        foreach ($attempt->question_ids as $qid) {
            $question = $questions->get($qid);
            if (! $question) {
                continue;
            }
            $answer = $answers[$qid] ?? $answers[(string) $qid] ?? null;
            $isCorrect = $answer !== null && $this->checker->check($question, $answer);

            $weight = match ($question->difficulty) {
                'easy' => 1.0,
                'hard' => 3.0,
                default => 2.0,
            };

            $total++;
            $weightTotal += $weight;
            if ($isCorrect) {
                $correct++;
                $weighted += $weight;
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

        $score = $total > 0 ? (int) round($correct / $total * 100) : 0;
        $weightedPct = $weightTotal > 0 ? $weighted / $weightTotal * 100 : 0;

        $levelCode = match (true) {
            $weightedPct >= 85 => 'b2',
            $weightedPct >= 70 => 'b1',
            $weightedPct >= 50 => 'a2',
            $weightedPct >= 30 => 'a1',
            default => 'beginner',
        };

        $level = CefrLevel::where('code', $levelCode)->firstOrFail();

        $attempt->fill([
            'answers' => $stored,
            'submitted_at' => now(),
            'score' => $score,
            'passed' => true,
            'skill_breakdown' => $breakdown,
        ])->save();

        $user = User::find($attempt->user_id);
        $this->applyPlacement($user, $level);

        return [
            'attempt' => $attempt->refresh(),
            'level' => $level,
            'score' => $score,
            'breakdown' => $breakdown,
        ];
    }

    /** Unlock the assessed level and everything below it. */
    public function applyPlacement(User $user, CefrLevel $level): void
    {
        $levels = CefrLevel::where('sort_order', '<=', $level->sort_order)->get();
        foreach ($levels as $l) {
            LevelUnlock::firstOrCreate(
                ['user_id' => $user->id, 'cefr_level_id' => $l->id],
                ['source' => 'placement', 'unlocked_at' => now()],
            );
        }

        $user->forceFill([
            'current_level_id' => $level->id,
            'placement_completed_at' => now(),
        ])->save();
    }
}
