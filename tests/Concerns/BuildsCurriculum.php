<?php

namespace Tests\Concerns;

use App\Models\CefrLevel;
use App\Models\Course;
use App\Models\Exercise;
use App\Models\Lesson;
use App\Models\LevelUnlock;
use App\Models\Module;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use Database\Seeders\CefrLevelSeeder;

trait BuildsCurriculum
{
    protected function seedLevels(): void
    {
        $this->seed(CefrLevelSeeder::class);
    }

    protected function level(string $code): CefrLevel
    {
        return CefrLevel::where('code', $code)->firstOrFail();
    }

    protected function makeLesson(string $levelCode = 'beginner', array $attrs = []): Lesson
    {
        $level = $this->level($levelCode);
        $course = Course::create([
            'cefr_level_id' => $level->id, 'skill' => 'grammar',
            'title' => 'Course '.uniqid(), 'slug' => 'course-'.uniqid(),
            'sort_order' => 1, 'is_published' => true,
        ]);
        $module = Module::create([
            'course_id' => $course->id, 'title' => 'Module '.uniqid(),
            'sort_order' => 1, 'is_published' => true,
        ]);

        return Lesson::create(array_merge([
            'module_id' => $module->id, 'title' => 'Lesson '.uniqid(),
            'slug' => 'lesson-'.uniqid(), 'sort_order' => 1,
            'xp_reward' => 20, 'estimated_minutes' => 10, 'is_published' => true,
        ], $attrs));
    }

    protected function makeExercise(Lesson $lesson, array $attrs = []): Exercise
    {
        return Exercise::create(array_merge([
            'lesson_id' => $lesson->id, 'title' => 'Exercise '.uniqid(),
            'kind' => 'practice', 'cefr_level_id' => $lesson->module->course->cefr_level_id,
            'skill' => 'grammar', 'question_count' => 0, 'xp_reward' => 10,
            'sort_order' => 1, 'is_published' => true,
        ], $attrs));
    }

    protected function makeMcq(Exercise $exercise, string $correct = 'works', array $wrong = ['work']): Question
    {
        $q = Question::create([
            'exercise_id' => $exercise->id, 'type' => 'multiple_choice', 'skill' => 'grammar',
            'prompt' => 'She ___ here. '.uniqid(), 'difficulty' => 'easy', 'sort_order' => 1,
        ]);
        QuestionOption::create(['question_id' => $q->id, 'text' => $correct, 'is_correct' => true, 'sort_order' => 1]);
        foreach ($wrong as $i => $w) {
            QuestionOption::create(['question_id' => $q->id, 'text' => $w, 'is_correct' => false, 'sort_order' => $i + 2]);
        }

        return $q->load('options');
    }

    protected function makeTextQuestion(Exercise $exercise, array $accepted, string $type = 'fill_blank'): Question
    {
        return Question::create([
            'exercise_id' => $exercise->id, 'type' => $type, 'skill' => 'grammar',
            'prompt' => 'Fill: '.uniqid(), 'answer_config' => ['accepted' => $accepted],
            'difficulty' => 'easy', 'sort_order' => 1,
        ]);
    }

    protected function unlock(User $user, string $levelCode): void
    {
        LevelUnlock::firstOrCreate(
            ['user_id' => $user->id, 'cefr_level_id' => $this->level($levelCode)->id],
            ['source' => 'initial', 'unlocked_at' => now()],
        );
    }

    /** Fill a level's exam pool with N simple MCQs. */
    protected function fillExamPool(string $levelCode, int $count): Exercise
    {
        $level = $this->level($levelCode);
        $pool = Exercise::create([
            'title' => strtoupper($levelCode).' Exam Pool', 'kind' => 'exam_pool',
            'cefr_level_id' => $level->id, 'question_count' => 0, 'xp_reward' => 0,
            'sort_order' => 1, 'is_published' => true,
        ]);
        for ($i = 0; $i < $count; $i++) {
            $this->makeMcq($pool, 'right'.$i, ['wrong'.$i]);
        }

        return $pool;
    }
}
