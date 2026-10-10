<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\Setting;
use App\Models\SpeakingAttempt;
use App\Models\WritingSubmission;
use App\Services\SpeakingScorer;
use App\Services\XpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PracticeController extends Controller
{
    public function __construct(
        private SpeakingScorer $scorer,
        private XpService $xp,
    ) {}

    /**
     * Score a speaking attempt from a transcript produced client-side by the
     * Web Speech API. No raw audio is uploaded or stored.
     */
    public function scoreSpeaking(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target' => ['required', 'string', 'max:500'],
            'transcript' => ['required', 'string', 'max:1000'],
            'question_id' => ['nullable', 'integer', 'exists:questions,id'],
        ]);

        $result = $this->scorer->score($data['target'], $data['transcript']);

        $attempt = SpeakingAttempt::create([
            'user_id' => $request->user()->id,
            'question_id' => $data['question_id'] ?? null,
            'target_text' => $data['target'],
            'transcript' => $data['transcript'],
            'accuracy' => $result['accuracy'],
            'feedback' => ['missing' => $result['missing'], 'extra' => $result['extra']],
        ]);

        $xp = 0;
        if ($result['accuracy'] >= 60) {
            $ref = 'speaking:'.md5($data['target']).':'.now($request->user()->timezone ?: 'UTC')->toDateString();
            $xp = $this->xp->award($request->user(), (int) Setting::get('xp_speaking', 5), 'speaking', $ref, mb_substr($data['target'], 0, 60));
        }

        return response()->json([
            'accuracy' => $result['accuracy'],
            'missing' => $result['missing'],
            'extra' => $result['extra'],
            'xp_awarded' => $xp,
            'note' => 'Score reflects word-level transcript similarity, not phonetic quality.',
        ]);
    }

    /** Save a writing submission and show the model answer for self-review. */
    public function submitWriting(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question_id' => ['required', 'integer', 'exists:questions,id'],
            'content' => ['required', 'string', 'min:10', 'max:10000'],
        ]);

        $question = Question::findOrFail($data['question_id']);
        abort_unless($question->type === 'writing' || ($question->answer_config['model_answer'] ?? null) !== null || $question->type === 'typing', 422, 'This question does not accept writing submissions.');

        $minWords = (int) ($question->answer_config['min_words'] ?? 5);
        $wordCount = str_word_count(strip_tags($data['content']));

        if ($wordCount < $minWords) {
            return response()->json([
                'message' => "Write at least {$minWords} words. You wrote {$wordCount}.",
            ], 422);
        }

        WritingSubmission::create([
            'user_id' => $request->user()->id,
            'question_id' => $question->id,
            'prompt' => $question->prompt,
            'content' => $data['content'],
            'word_count' => $wordCount,
        ]);

        $ref = 'writing:'.$question->id;
        $xp = $this->xp->award($request->user(), (int) Setting::get('xp_writing', 15), 'writing', $ref, mb_substr($question->prompt, 0, 60));

        return response()->json([
            'submitted' => true,
            'word_count' => $wordCount,
            'model_answer' => $question->answer_config['model_answer'] ?? null,
            'self_review_points' => $question->answer_config['review_points'] ?? [],
            'xp_awarded' => $xp,
        ]);
    }

    public function speakingHistory(Request $request): JsonResponse
    {
        $attempts = SpeakingAttempt::where('user_id', $request->user()->id)
            ->latest()->limit(20)
            ->get(['id', 'target_text', 'transcript', 'accuracy', 'feedback', 'created_at']);

        return response()->json(['attempts' => $attempts]);
    }
}
