<?php

namespace Database\Seeders;

use App\Models\CefrLevel;
use App\Models\Course;
use App\Models\Exercise;
use App\Models\Lesson;
use App\Models\LessonSection;
use App\Models\Module;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Support\Str;

/**
 * Turns a structured content array into curriculum records.
 *
 * Format:
 * [
 *   'level' => 'a1',
 *   'courses' => [[
 *     'skill' => 'grammar', 'title' => ..., 'description' => ...,
 *     'modules' => [[
 *       'title' => ..., 'description' => ...,
 *       'lessons' => [[
 *         'title' => ..., 'objective' => ..., 'xp' => 20, 'minutes' => 10,
 *         'prerequisites' => ['other-lesson-slug'],
 *         'sections' => [[ 'type' => ..., 'title' => ..., 'body' => ..., 'body_id' => ..., 'meta' => [...] ]],
 *         'exercises' => [[
 *           'title' => ..., 'xp' => 10, 'pick' => 0,
 *           'questions' => [[ 'type' => ..., 'prompt' => ..., ... 'options' => [['text', true]] ]],
 *         ]],
 *       ]],
 *     ]],
 *   ]],
 *   'exam_pool' => [ 'title' => ..., 'questions' => [...] ],
 * ]
 */
class CurriculumBuilder
{
    public function build(array $data): void
    {
        $level = CefrLevel::where('code', $data['level'])->firstOrFail();

        foreach ($data['courses'] ?? [] as $ci => $courseData) {
            $course = Course::updateOrCreate(
                ['slug' => Str::slug($level->code.' '.$courseData['title'])],
                [
                    'cefr_level_id' => $level->id,
                    'skill' => $courseData['skill'],
                    'title' => $courseData['title'],
                    'description' => $courseData['description'] ?? null,
                    'sort_order' => $ci + 1,
                    'is_published' => true,
                ],
            );

            foreach ($courseData['modules'] ?? [] as $mi => $moduleData) {
                $module = Module::updateOrCreate(
                    ['course_id' => $course->id, 'title' => $moduleData['title']],
                    [
                        'description' => $moduleData['description'] ?? null,
                        'sort_order' => $mi + 1,
                        'is_published' => true,
                    ],
                );

                foreach ($moduleData['lessons'] ?? [] as $li => $lessonData) {
                    $slug = Str::slug($level->code.' '.$lessonData['title']);
                    $lesson = Lesson::updateOrCreate(
                        ['slug' => $slug],
                        [
                            'module_id' => $module->id,
                            'title' => $lessonData['title'],
                            'objective' => $lessonData['objective'] ?? null,
                            'sort_order' => $li + 1,
                            'xp_reward' => $lessonData['xp'] ?? 20,
                            'estimated_minutes' => $lessonData['minutes'] ?? 10,
                            'is_published' => true,
                        ],
                    );

                    $lesson->sections()->delete();
                    foreach ($lessonData['sections'] ?? [] as $si => $sectionData) {
                        LessonSection::create([
                            'lesson_id' => $lesson->id,
                            'type' => $sectionData['type'],
                            'title' => $sectionData['title'] ?? null,
                            'body' => $sectionData['body'] ?? null,
                            'body_id' => $sectionData['body_id'] ?? null,
                            'meta' => $sectionData['meta'] ?? null,
                            'sort_order' => $si + 1,
                        ]);
                    }

                    foreach ($lessonData['exercises'] ?? [] as $ei => $exerciseData) {
                        $exercise = Exercise::updateOrCreate(
                            ['lesson_id' => $lesson->id, 'title' => $exerciseData['title']],
                            [
                                'kind' => 'practice',
                                'cefr_level_id' => $level->id,
                                'skill' => $courseData['skill'],
                                'question_count' => $exerciseData['pick'] ?? 0,
                                'xp_reward' => $exerciseData['xp'] ?? 10,
                                'sort_order' => $ei + 1,
                                'is_published' => true,
                            ],
                        );
                        $this->syncQuestions($exercise, $exerciseData['questions'] ?? [], $courseData['skill']);
                    }

                    // Defer prerequisites: resolved after all lessons exist.
                    if (! empty($lessonData['prerequisites'])) {
                        $this->pendingPrereqs[] = [$slug, $level->code, $lessonData['prerequisites']];
                    }
                }
            }
        }

        if (! empty($data['exam_pool'])) {
            $exercise = Exercise::updateOrCreate(
                ['title' => $data['exam_pool']['title'], 'kind' => 'exam_pool', 'cefr_level_id' => $level->id],
                ['skill' => null, 'question_count' => 0, 'xp_reward' => 0, 'is_published' => true],
            );
            $this->syncQuestions($exercise, $data['exam_pool']['questions'] ?? [], null);
        }

        $this->resolvePrerequisites();
    }

    private array $pendingPrereqs = [];

    public function buildPlacementPool(array $questions): void
    {
        $exercise = Exercise::updateOrCreate(
            ['title' => 'Placement Assessment Pool', 'kind' => 'placement'],
            ['question_count' => 0, 'xp_reward' => 0, 'is_published' => true],
        );
        $this->syncQuestions($exercise, $questions, null);
    }

    private function syncQuestions(Exercise $exercise, array $questions, ?string $defaultSkill): void
    {
        $exercise->questions()->delete();

        foreach ($questions as $qi => $q) {
            $question = Question::create([
                'exercise_id' => $exercise->id,
                'type' => $q['type'],
                'skill' => $q['skill'] ?? $defaultSkill ?? 'general',
                'prompt' => $q['prompt'],
                'passage' => $q['passage'] ?? null,
                'audio_text' => $q['audio_text'] ?? null,
                'explanation' => $q['explanation'] ?? null,
                'explanation_id' => $q['explanation_id'] ?? null,
                'answer_config' => $q['config'] ?? null,
                'difficulty' => $q['difficulty'] ?? 'medium',
                'sort_order' => $qi + 1,
            ]);

            foreach ($q['options'] ?? [] as $oi => $option) {
                QuestionOption::create([
                    'question_id' => $question->id,
                    'text' => $option[0],
                    'is_correct' => (bool) ($option[1] ?? false),
                    'sort_order' => $oi + 1,
                ]);
            }
        }
    }

    private function resolvePrerequisites(): void
    {
        foreach ($this->pendingPrereqs as [$slug, $levelCode, $prereqTitles]) {
            $lesson = Lesson::where('slug', $slug)->first();
            if (! $lesson) {
                continue;
            }
            $ids = [];
            foreach ($prereqTitles as $title) {
                $prereq = Lesson::where('slug', Str::slug($levelCode.' '.$title))->first();
                if ($prereq && $prereq->id !== $lesson->id) {
                    $ids[] = $prereq->id;
                }
            }
            $lesson->prerequisites()->sync($ids);
        }
        $this->pendingPrereqs = [];
    }
}
