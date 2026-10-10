<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CefrLevel;
use App\Models\ExamAttempt;
use App\Models\Lesson;
use App\Models\Question;
use App\Services\ExamService;
use App\Services\PlacementService;
use App\Services\ProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    public function __construct(
        private ExamService $exams,
        private PlacementService $placement,
        private ProgressService $progress,
    ) {}

    public function startFinal(Request $request, CefrLevel $level): JsonResponse
    {
        $user = $request->user();
        abort_unless($level->has_exam, 422, 'This level has no final exam.');
        abort_unless($this->progress->isLevelUnlocked($user, $level), 403, 'This level is locked.');
        abort_unless($this->progress->contentComplete($user, $level), 422, 'Complete all lessons in this level before taking the final exam.');

        $attempt = $this->exams->startFinalExam($user, $level);

        return response()->json($this->attemptPayload($attempt));
    }

    public function startPlacement(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->placement_completed_at !== null, 422, 'You have already completed the placement test.');

        $attempt = $this->placement->start($user);

        return response()->json($this->attemptPayload($attempt));
    }

    public function active(Request $request, ExamAttempt $attempt): JsonResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);
        abort_unless($attempt->isActive(), 422, 'This attempt is no longer active.');

        return response()->json($this->attemptPayload($attempt));
    }

    public function submit(Request $request, ExamAttempt $attempt): JsonResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);

        $data = $request->validate(['answers' => ['present', 'array']]);

        if ($attempt->kind === 'placement') {
            $result = $this->placement->submit($attempt, $data['answers']);

            return response()->json([
                'kind' => 'placement',
                'score' => $result['score'],
                'level' => $result['level']->only(['id', 'code', 'name', 'description']),
                'breakdown' => $this->breakdownPayload($result['breakdown']),
            ]);
        }

        $graded = $this->exams->submit($attempt, $data['answers']);

        $payload = [
            'kind' => 'final',
            'score' => $graded->score,
            'passed' => $graded->passed,
            'pass_score' => $graded->level->exam_pass_score,
            'level' => $graded->level->only(['id', 'code', 'name']),
            'breakdown' => $this->breakdownPayload($graded->skill_breakdown ?? []),
        ];

        if (! $graded->passed) {
            $weak = $this->progress->weakSkills($graded);
            $payload['weak_skills'] = $weak;
            $payload['recommended_lessons'] = $this->recommendLessons($graded->level, $weak);
        }

        return response()->json($payload);
    }

    /** Placement result summary + option to start at assessed level or reinforce lower level. */
    public function placementResult(Request $request): JsonResponse
    {
        $user = $request->user();
        $attempt = ExamAttempt::where('user_id', $user->id)
            ->where('kind', 'placement')
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')->first();

        abort_unless($attempt, 404, 'No completed placement test found.');

        return response()->json([
            'score' => $attempt->score,
            'level' => $user->currentLevel?->only(['id', 'code', 'name', 'description']),
            'breakdown' => $this->breakdownPayload($attempt->skill_breakdown ?? []),
            'submitted_at' => $attempt->submitted_at,
        ]);
    }

    /** After placement, the user may opt to start one level below for reinforcement. */
    public function chooseStart(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->placement_completed_at !== null, 422, 'Complete the placement test first.');

        $data = $request->validate(['level_id' => ['required', 'integer', 'exists:cefr_levels,id']]);
        $level = CefrLevel::findOrFail($data['level_id']);

        abort_unless($this->progress->isLevelUnlocked($user, $level), 403, 'You can only start at an unlocked level.');

        $user->forceFill(['current_level_id' => $level->id])->save();

        return response()->json(['current_level' => $level->only(['id', 'code', 'name'])]);
    }

    public function history(Request $request): JsonResponse
    {
        $attempts = ExamAttempt::with('level:id,code,name')
            ->where('user_id', $request->user()->id)
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->limit(30)
            ->get(['id', 'cefr_level_id', 'kind', 'score', 'passed', 'submitted_at']);

        return response()->json(['attempts' => $attempts]);
    }

    private function attemptPayload(ExamAttempt $attempt): array
    {
        $questions = Question::with('options')
            ->whereIn('id', $attempt->question_ids)
            ->get()
            ->keyBy('id');

        $ordered = collect($attempt->question_ids)
            ->map(fn ($id) => $questions->get($id))
            ->filter()
            ->map(fn ($q) => LearnController::publicQuestionPayload($q))
            ->values();

        return [
            'attempt_id' => $attempt->id,
            'kind' => $attempt->kind,
            'level' => $attempt->level->only(['id', 'code', 'name']),
            'started_at' => $attempt->started_at,
            'expires_at' => $attempt->expires_at,
            'seconds_remaining' => $attempt->expires_at ? max(0, (int) now()->diffInSeconds($attempt->expires_at, false)) : null,
            'questions' => $ordered,
        ];
    }

    private function breakdownPayload(array $breakdown): array
    {
        $result = [];
        foreach ($breakdown as $skill => $stats) {
            $result[] = [
                'skill' => $skill,
                'correct' => $stats['correct'],
                'total' => $stats['total'],
                'percent' => $stats['total'] > 0 ? (int) round($stats['correct'] / $stats['total'] * 100) : 0,
            ];
        }
        usort($result, fn ($a, $b) => $b['percent'] <=> $a['percent']);

        return $result;
    }

    private function recommendLessons(CefrLevel $level, array $weakSkills): array
    {
        if (count($weakSkills) === 0) {
            return [];
        }

        return Lesson::where('is_published', true)
            ->whereHas('module.course', function ($q) use ($level, $weakSkills) {
                $q->where('cefr_level_id', $level->id)->whereIn('skill', $weakSkills);
            })
            ->limit(6)
            ->get(['id', 'title', 'slug'])
            ->toArray();
    }
}
