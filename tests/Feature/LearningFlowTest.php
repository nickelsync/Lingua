<?php

namespace Tests\Feature;

use App\Models\ExerciseAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCurriculum;
use Tests\TestCase;

class LearningFlowTest extends TestCase
{
    use BuildsCurriculum, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLevels();
        $this->user = User::factory()->create();
        $this->unlock($this->user, 'beginner');
    }

    public function test_locked_level_returns_403(): void
    {
        $b2 = $this->level('b2');
        $this->actingAs($this->user)->getJson("/api/levels/{$b2->id}")->assertForbidden();
    }

    public function test_unlocked_level_lists_courses_and_lessons(): void
    {
        $lesson = $this->makeLesson('beginner');
        $level = $this->level('beginner');

        $this->actingAs($this->user)->getJson("/api/levels/{$level->id}")
            ->assertOk()
            ->assertJsonPath('courses.0.modules.0.lessons.0.title', $lesson->title);
    }

    public function test_unpublished_lessons_are_hidden(): void
    {
        $lesson = $this->makeLesson('beginner', ['is_published' => false]);

        $this->actingAs($this->user)->getJson("/api/lessons/{$lesson->slug}")->assertNotFound();
    }

    public function test_answer_check_gives_immediate_feedback_with_explanation(): void
    {
        $lesson = $this->makeLesson('beginner');
        $exercise = $this->makeExercise($lesson);
        $q = $this->makeMcq($exercise);
        $q->update(['explanation' => 'Third person singular takes -s.']);
        $wrong = $q->options->firstWhere('is_correct', false);

        $res = $this->actingAs($this->user)->postJson("/api/questions/{$q->id}/check", ['answer' => $wrong->id]);
        $res->assertOk()
            ->assertJsonPath('correct', false)
            ->assertJsonPath('explanation', 'Third person singular takes -s.')
            ->assertJsonPath('correct_answer', 'works');
    }

    public function test_exam_pool_questions_cannot_be_checked_live(): void
    {
        $pool = $this->fillExamPool('beginner', 1);
        $q = $pool->questions()->first();

        $this->actingAs($this->user)->postJson("/api/questions/{$q->id}/check", ['answer' => 1])
            ->assertForbidden();
    }

    public function test_exercise_submission_scores_and_awards_xp(): void
    {
        $lesson = $this->makeLesson('beginner');
        $exercise = $this->makeExercise($lesson, ['xp_reward' => 10]);
        $q1 = $this->makeMcq($exercise);
        $q2 = $this->makeTextQuestion($exercise, ['drink']);

        $res = $this->actingAs($this->user)->postJson("/api/exercises/{$exercise->id}/submit", [
            'answers' => [
                $q1->id => $q1->options->firstWhere('is_correct', true)->id,
                $q2->id => 'DRINK',
            ],
        ]);

        $res->assertOk()->assertJsonPath('score', 100)->assertJsonPath('xp_awarded', 10);
        $this->assertSame(10, $this->user->refresh()->xp_total);
        $this->assertSame(1, $this->user->streak_current);
    }

    public function test_repeat_exercise_xp_is_reduced_to_10_percent(): void
    {
        $lesson = $this->makeLesson('beginner');
        $exercise = $this->makeExercise($lesson, ['xp_reward' => 10]);
        $q = $this->makeMcq($exercise);
        $answer = ['answers' => [$q->id => $q->options->firstWhere('is_correct', true)->id]];

        $this->actingAs($this->user)->postJson("/api/exercises/{$exercise->id}/submit", $answer)
            ->assertJsonPath('xp_awarded', 10);
        $this->actingAs($this->user)->postJson("/api/exercises/{$exercise->id}/submit", $answer)
            ->assertJsonPath('xp_awarded', 1);
    }

    public function test_lesson_completion_requires_all_exercises_attempted(): void
    {
        $lesson = $this->makeLesson('beginner', ['xp_reward' => 20]);
        $ex1 = $this->makeExercise($lesson);
        $ex2 = $this->makeExercise($lesson);
        $q1 = $this->makeMcq($ex1);
        $this->makeMcq($ex2);

        $this->actingAs($this->user)->postJson("/api/lessons/{$lesson->id}/complete")
            ->assertUnprocessable()
            ->assertJsonPath('exercises_done', 0)
            ->assertJsonPath('exercises_total', 2);

        ExerciseAttempt::create(['user_id' => $this->user->id, 'exercise_id' => $ex1->id, 'score' => 100, 'correct_count' => 1, 'question_count' => 1]);
        ExerciseAttempt::create(['user_id' => $this->user->id, 'exercise_id' => $ex2->id, 'score' => 100, 'correct_count' => 1, 'question_count' => 1]);

        $this->actingAs($this->user)->postJson("/api/lessons/{$lesson->id}/complete")
            ->assertOk()->assertJsonPath('first_time', true)->assertJsonPath('xp_awarded', 20);

        $this->assertDatabaseHas('lesson_completions', ['user_id' => $this->user->id, 'lesson_id' => $lesson->id]);
    }

    public function test_prerequisites_block_lesson_completion(): void
    {
        $first = $this->makeLesson('beginner');
        $second = $this->makeLesson('beginner');
        $second->prerequisites()->attach($first->id);

        $this->actingAs($this->user)->postJson("/api/lessons/{$second->id}/complete")
            ->assertForbidden();

        // Lesson detail also reports the block.
        $this->actingAs($this->user)->getJson("/api/lessons/{$second->slug}")
            ->assertForbidden()->assertJsonStructure(['missing_prerequisites']);
    }
}
