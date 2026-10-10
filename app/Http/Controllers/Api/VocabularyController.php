<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\UserVocabulary;
use App\Models\Vocabulary;
use App\Services\AchievementService;
use App\Services\SpacedRepetitionService;
use App\Services\XpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VocabularyController extends Controller
{
    public function __construct(
        private SpacedRepetitionService $srs,
        private XpService $xp,
    ) {}

    /** Browse the global vocabulary bank with filters. */
    public function index(Request $request): JsonResponse
    {
        $query = Vocabulary::with('level:id,code,name');

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->whereLike('word', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('definition', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('meaning_id', "%{$search}%", caseSensitive: false);
            });
        }
        if ($level = $request->query('level')) {
            $query->whereHas('level', fn ($q) => $q->where('code', $level));
        }
        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }
        if ($difficulty = $request->query('difficulty')) {
            $query->where('difficulty', $difficulty);
        }

        $savedIds = UserVocabulary::where('user_id', $request->user()->id)->pluck('vocabulary_id')->all();

        $page = $query->orderBy('word')->paginate(24);

        return response()->json([
            'data' => collect($page->items())->map(fn ($v) => $this->wordPayload($v, in_array($v->id, $savedIds, true))),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'categories' => Vocabulary::whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    /** The user's saved words. */
    public function mine(Request $request): JsonResponse
    {
        $query = UserVocabulary::with(['vocabulary.level:id,code,name'])
            ->where('user_id', $request->user()->id);

        if ($search = $request->query('q')) {
            $query->whereHas('vocabulary', fn ($q) => $q->whereLike('word', "%{$search}%", caseSensitive: false)->orWhereLike('meaning_id', "%{$search}%", caseSensitive: false));
        }
        if ($category = $request->query('category')) {
            $query->whereHas('vocabulary', fn ($q) => $q->where('category', $category));
        }

        $page = $query->latest()->paginate(24);

        return response()->json([
            'data' => collect($page->items())->map(function ($entry) {
                $payload = $this->wordPayload($entry->vocabulary, true);
                $payload['srs'] = [
                    'review_count' => $entry->review_count,
                    'correct_count' => $entry->correct_count,
                    'incorrect_count' => $entry->incorrect_count,
                    'next_review_at' => $entry->next_review_at,
                    'due' => $entry->next_review_at === null || $entry->next_review_at->isPast(),
                ];

                return $payload;
            }),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'due_count' => $this->srs->dueCount($request->user()->id),
        ]);
    }

    public function save(Request $request, Vocabulary $vocabulary): JsonResponse
    {
        $entry = UserVocabulary::firstOrCreate(
            ['user_id' => $request->user()->id, 'vocabulary_id' => $vocabulary->id],
            ['next_review_at' => now()],
        );

        if ($entry->wasRecentlyCreated) {
            app(AchievementService::class)->checkAll($request->user());
        }

        return response()->json(['saved' => true], $entry->wasRecentlyCreated ? 201 : 200);
    }

    public function remove(Request $request, Vocabulary $vocabulary): JsonResponse
    {
        UserVocabulary::where('user_id', $request->user()->id)
            ->where('vocabulary_id', $vocabulary->id)->delete();

        return response()->json(['saved' => false]);
    }

    /** Flashcard review queue: due words first. */
    public function reviewQueue(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $limit = min(50, (int) $request->query('limit', 20));

        $due = UserVocabulary::with('vocabulary')
            ->where('user_id', $userId)
            ->where(fn ($q) => $q->whereNull('next_review_at')->orWhere('next_review_at', '<=', now()))
            ->orderByRaw('next_review_at NULLS FIRST')
            ->limit($limit)
            ->get();

        return response()->json([
            'due_count' => $this->srs->dueCount($userId),
            'cards' => $due->map(fn ($entry) => [
                'entry_id' => $entry->id,
                'word' => $this->wordPayload($entry->vocabulary, true),
            ]),
        ]);
    }

    /** Grade a flashcard: quality 0..3 (again, hard, good, easy). */
    public function review(Request $request, UserVocabulary $entry): JsonResponse
    {
        abort_unless($entry->user_id === $request->user()->id, 403);

        $data = $request->validate(['quality' => ['required', 'integer', 'between:0,3']]);

        $entry = $this->srs->review($entry, $data['quality']);

        // XP per review, capped naturally by due-scheduling; one grant per day per word.
        $xp = 0;
        if ($data['quality'] > 0) {
            $ref = 'vocab:'.$entry->vocabulary_id.':'.now($request->user()->timezone ?: 'UTC')->toDateString();
            $xp = $this->xp->award($request->user(), (int) Setting::get('xp_vocab_review', 2), 'vocab_review', $ref, $entry->vocabulary->word);
        }

        return response()->json([
            'next_review_at' => $entry->next_review_at,
            'interval_days' => $entry->interval_days,
            'xp_awarded' => $xp,
            'due_count' => $this->srs->dueCount($request->user()->id),
        ]);
    }

    private function wordPayload(Vocabulary $v, bool $saved): array
    {
        return [
            'id' => $v->id,
            'word' => $v->word,
            'part_of_speech' => $v->part_of_speech,
            'ipa' => $v->ipa,
            'definition' => $v->definition,
            'meaning_id' => $v->meaning_id,
            'example' => $v->example,
            'category' => $v->category,
            'difficulty' => $v->difficulty,
            'level' => $v->level?->only(['code', 'name']),
            'audio_path' => $v->audio_path ? '/storage/'.$v->audio_path : null,
            'usage_note' => $v->usage_note,
            'saved' => $saved,
        ];
    }
}
