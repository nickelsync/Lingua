<?php

namespace Tests\Feature;

use App\Models\ExamAttempt;
use App\Models\ExerciseAttempt;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCurriculum;
use Tests\TestCase;

class ExamTest extends TestCase
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

    /** Complete all beginner lessons so the exam gate opens. */
    private function completeBeginnerContent(): void
    {
        $lesson = $this->makeLesson('beginner');
        $ex = $this->makeExercise($lesson);
        $q = $this->makeMcq($ex);
        ExerciseAttempt::create(['user_id' => $this->user->id, 'exercise_id' => $ex->id, 'score' => 100, 'correct_count' => 1, 'question_count' => 1]);
        $this->actingAs($this->user)->postJson("/api/lessons/{$lesson->id}/complete")->assertOk();
    }

    public function test_exam_blocked_until_content_complete(): void
    {
        $this->makeLesson('beginner');
        $this->fillExamPool('beginner', 20);
        $level = $this->level('beginner');

        $this->actingAs($this->user)->postJson("/api/levels/{$level->id}/exam/start")
            ->assertUnprocessable();
    }

    public function test_exam_blocked_for_locked_level(): void
    {
        $level = $this->level('b1');
        $this->actingAs($this->user)->postJson("/api/levels/{$level->id}/exam/start")->assertForbidden();
    }

    public function test_exam_start_freezes_questions_and_deadline(): void
    {
        $this->completeBeginnerContent();
        $this->fillExamPool('beginner', 25);
        $level = $this->level('beginner');

        $res = $this->actingAs($this->user)->postJson("/api/levels/{$level->id}/exam/start");
        $res->assertOk();

        $attempt = ExamAttempt::where('user_id', $this->user->id)->where('kind', 'final')->first();
        $this->assertCount(20, $attempt->question_ids); // level exam_question_count
        $this->assertNotNull($attempt->expires_at);

        // Question payloads never include the correct answer.
        $this->assertStringNotContainsString('is_correct', $res->getContent());

        // Restarting returns the same active attempt.
        $again = $this->actingAs($this->user)->postJson("/api/levels/{$level->id}/exam/start");
        $this->assertSame($attempt->id, ExamAttempt::where('user_id', $this->user->id)->where('kind', 'final')->latest('id')->first()->id);
    }

    public function test_passing_exam_unlocks_next_level(): void
    {
        $this->completeBeginnerContent();
        $this->fillExamPool('beginner', 25);
        $level = $this->level('beginner');

        $this->actingAs($this->user)->postJson("/api/levels/{$level->id}/exam/start")->assertOk();
        $attempt = ExamAttempt::where('user_id', $this->user->id)->where('kind', 'final')->first();

        $answers = $this->correctAnswers($attempt);

        $res = $this->actingAs($this->user)->postJson("/api/exams/{$attempt->id}/submit", ['answers' => $answers]);
        $res->assertOk()->assertJsonPath('score', 100)->assertJsonPath('passed', true);

        $this->user->refresh();
        $this->assertSame('a1', $this->user->currentLevel->code);
        $this->assertDatabaseHas('level_unlocks', ['user_id' => $this->user->id, 'cefr_level_id' => $this->level('a1')->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->user->id, 'type' => 'level']);
    }

    public function test_failing_exam_keeps_level_locked_and_allows_retake(): void
    {
        $this->completeBeginnerContent();
        $this->fillExamPool('beginner', 25);
        $level = $this->level('beginner');

        $this->actingAs($this->user)->postJson("/api/levels/{$level->id}/exam/start")->assertOk();
        $attempt = ExamAttempt::where('user_id', $this->user->id)->where('kind', 'final')->first();

        $res = $this->actingAs($this->user)->postJson("/api/exams/{$attempt->id}/submit", ['answers' => []]);
        $res->assertOk()->assertJsonPath('passed', false);

        $this->assertDatabaseMissing('level_unlocks', ['user_id' => $this->user->id, 'cefr_level_id' => $this->level('a1')->id]);

        // Retake creates a new attempt.
        $this->actingAs($this->user)->postJson("/api/levels/{$level->id}/exam/start")->assertOk();
        $this->assertSame(2, ExamAttempt::where('user_id', $this->user->id)->where('kind', 'final')->count());
    }

    public function test_expired_exam_grades_saved_answers_as_null(): void
    {
        $this->completeBeginnerContent();
        $this->fillExamPool('beginner', 25);
        $level = $this->level('beginner');

        $this->actingAs($this->user)->postJson("/api/levels/{$level->id}/exam/start")->assertOk();
        $attempt = ExamAttempt::where('user_id', $this->user->id)->where('kind', 'final')->first();
        $attempt->update(['expires_at' => now()->subMinutes(5)]);

        $answers = $this->correctAnswers($attempt);
        $res = $this->actingAs($this->user)->postJson("/api/exams/{$attempt->id}/submit", ['answers' => $answers]);
        $res->assertOk()->assertJsonPath('score', 0)->assertJsonPath('passed', false);
    }

    public function test_double_submission_rejected(): void
    {
        $this->completeBeginnerContent();
        $this->fillExamPool('beginner', 25);
        $level = $this->level('beginner');

        $this->actingAs($this->user)->postJson("/api/levels/{$level->id}/exam/start")->assertOk();
        $attempt = ExamAttempt::where('user_id', $this->user->id)->where('kind', 'final')->first();

        $this->actingAs($this->user)->postJson("/api/exams/{$attempt->id}/submit", ['answers' => []])->assertOk();
        $this->actingAs($this->user)->postJson("/api/exams/{$attempt->id}/submit", ['answers' => []])->assertUnprocessable();
    }

    public function test_users_cannot_touch_other_users_attempts(): void
    {
        $this->completeBeginnerContent();
        $this->fillExamPool('beginner', 25);
        $level = $this->level('beginner');
        $this->actingAs($this->user)->postJson("/api/levels/{$level->id}/exam/start")->assertOk();
        $attempt = ExamAttempt::where('user_id', $this->user->id)->first();

        $other = User::factory()->create();
        $this->actingAs($other)->getJson("/api/exams/{$attempt->id}")->assertForbidden();
        $this->actingAs($other)->postJson("/api/exams/{$attempt->id}/submit", ['answers' => []])->assertForbidden();
    }

    private function correctAnswers(ExamAttempt $attempt): array
    {
        $answers = [];
        foreach ($attempt->question_ids as $qid) {
            $q = Question::with('options')->find($qid);
            $answers[$qid] = $q->options->firstWhere('is_correct', true)->id;
        }

        return $answers;
    }
}
