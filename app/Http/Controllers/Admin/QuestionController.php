<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuestionController extends Controller
{
    private const TYPES = ['multiple_choice', 'fill_blank', 'true_false', 'matching', 'rearrange', 'translation', 'typing', 'listening', 'speaking', 'reading', 'writing'];

    private const SKILLS = ['grammar', 'vocabulary', 'listening', 'speaking', 'reading', 'writing', 'pronunciation', 'everyday', 'general'];

    // ---- Exercises ----
    public function exercises(Request $request): JsonResponse
    {
        $query = Exercise::with('lesson:id,title', 'level:id,code')->withCount('questions');
        if ($kind = $request->query('kind')) {
            $query->where('kind', $kind);
        }
        if ($lessonId = $request->query('lesson_id')) {
            $query->where('lesson_id', $lessonId);
        }
        if ($levelId = $request->query('level_id')) {
            $query->where('cefr_level_id', $levelId);
        }
        if ($search = $request->query('q')) {
            $query->whereLike('title', "%{$search}%", caseSensitive: false);
        }

        return response()->json(['exercises' => $query->orderBy('id', 'desc')->paginate(30)]);
    }

    public function storeExercise(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lesson_id' => ['nullable', 'exists:lessons,id'],
            'title' => ['required', 'string', 'max:150'],
            'kind' => ['required', Rule::in(['practice', 'exam_pool', 'placement'])],
            'cefr_level_id' => ['nullable', 'exists:cefr_levels,id'],
            'skill' => ['nullable', Rule::in(self::SKILLS)],
            'question_count' => ['integer', 'between:0,100'],
            'xp_reward' => ['integer', 'between:0,200'],
            'is_published' => ['boolean'],
        ]);
        $data['sort_order'] = (Exercise::where('lesson_id', $data['lesson_id'] ?? null)->max('sort_order') ?? 0) + 1;
        $data['xp_reward'] ??= (int) Setting::get('xp_exercise_default', 10);

        return response()->json(['exercise' => Exercise::create($data)], 201);
    }

    public function updateExercise(Request $request, Exercise $exercise): JsonResponse
    {
        $data = $request->validate([
            'lesson_id' => ['sometimes', 'nullable', 'exists:lessons,id'],
            'title' => ['sometimes', 'string', 'max:150'],
            'kind' => ['sometimes', Rule::in(['practice', 'exam_pool', 'placement'])],
            'cefr_level_id' => ['sometimes', 'nullable', 'exists:cefr_levels,id'],
            'skill' => ['sometimes', 'nullable', Rule::in(self::SKILLS)],
            'question_count' => ['sometimes', 'integer', 'between:0,100'],
            'xp_reward' => ['sometimes', 'integer', 'between:0,200'],
            'is_published' => ['sometimes', 'boolean'],
        ]);
        $exercise->update($data);

        return response()->json(['exercise' => $exercise]);
    }

    public function deleteExercise(Exercise $exercise): JsonResponse
    {
        $exercise->delete();

        return response()->json(['message' => 'Exercise deleted.']);
    }

    // ---- Questions ----
    public function questions(Request $request, Exercise $exercise): JsonResponse
    {
        return response()->json(['questions' => $exercise->questions()->with('options')->get()]);
    }

    public function storeQuestion(Request $request, Exercise $exercise): JsonResponse
    {
        $data = $this->validateQuestion($request);

        $question = DB::transaction(function () use ($exercise, $data) {
            $question = Question::create([
                ...collect($data)->except('options')->all(),
                'exercise_id' => $exercise->id,
                'sort_order' => ($exercise->questions()->max('sort_order') ?? 0) + 1,
            ]);

            foreach ($data['options'] ?? [] as $i => $option) {
                QuestionOption::create([
                    'question_id' => $question->id,
                    'text' => $option['text'],
                    'is_correct' => (bool) ($option['is_correct'] ?? false),
                    'sort_order' => $i + 1,
                ]);
            }

            return $question;
        });

        return response()->json(['question' => $question->load('options')], 201);
    }

    public function updateQuestion(Request $request, Question $question): JsonResponse
    {
        $data = $this->validateQuestion($request);

        DB::transaction(function () use ($question, $data) {
            $question->update(collect($data)->except('options')->all());

            if (array_key_exists('options', $data)) {
                $question->options()->delete();
                foreach ($data['options'] ?? [] as $i => $option) {
                    QuestionOption::create([
                        'question_id' => $question->id,
                        'text' => $option['text'],
                        'is_correct' => (bool) ($option['is_correct'] ?? false),
                        'sort_order' => $i + 1,
                    ]);
                }
            }
        });

        return response()->json(['question' => $question->refresh()->load('options')]);
    }

    public function deleteQuestion(Question $question): JsonResponse
    {
        $question->delete();

        return response()->json(['message' => 'Question deleted.']);
    }

    private function validateQuestion(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(self::TYPES)],
            'skill' => ['nullable', Rule::in(self::SKILLS)],
            'prompt' => ['required', 'string'],
            'passage' => ['nullable', 'string'],
            'audio_text' => ['nullable', 'string', 'max:255'],
            'explanation' => ['nullable', 'string'],
            'explanation_id' => ['nullable', 'string'],
            'answer_config' => ['nullable', 'array'],
            'difficulty' => ['required', Rule::in(['easy', 'medium', 'hard'])],
            'options' => ['sometimes', 'array', 'max:8'],
            'options.*.text' => ['required_with:options', 'string'],
            'options.*.is_correct' => ['boolean'],
        ]);

        // Type-specific integrity checks.
        $type = $data['type'];
        if (in_array($type, ['multiple_choice', 'listening', 'reading'], true)) {
            $options = $data['options'] ?? [];
            $correct = collect($options)->where('is_correct', true)->count();
            abort_if(count($options) < 2 || $correct !== 1, 422, 'Choice questions need at least 2 options and exactly 1 correct option.');
        }
        if (in_array($type, ['fill_blank', 'translation', 'typing'], true)) {
            abort_if(empty($data['answer_config']['accepted']), 422, 'Text questions need at least one accepted answer.');
        }
        if ($type === 'rearrange') {
            abort_if(count($data['answer_config']['order'] ?? []) < 2, 422, 'Rearrange questions need an ordered token list.');
        }
        if ($type === 'matching') {
            abort_if(count($data['answer_config']['pairs'] ?? []) < 2, 422, 'Matching questions need at least 2 pairs.');
        }
        if ($type === 'true_false') {
            abort_unless(array_key_exists('correct', $data['answer_config'] ?? []), 422, 'True/false questions need a correct value.');
        }

        return $data;
    }
}
