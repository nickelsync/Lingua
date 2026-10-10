<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\SpeakingAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsCurriculum;
use Tests\TestCase;

class PracticeTest extends TestCase
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

    public function test_speaking_score_is_honest_about_transcript_similarity(): void
    {
        $res = $this->actingAs($this->user)->postJson('/api/speaking/score', [
            'target' => 'I would like a cup of coffee',
            'transcript' => 'I would like a cup of tea',
        ]);

        $res->assertOk()
            ->assertJsonPath('missing.0', 'coffee')
            ->assertJsonPath('extra.0', 'tea');
        $this->assertStringContainsString('not phonetic quality', $res->json('note'));
        $this->assertSame(86, $res->json('accuracy')); // 6/7 words
    }

    public function test_speaking_attempt_stores_transcript_never_audio(): void
    {
        $this->actingAs($this->user)->postJson('/api/speaking/score', [
            'target' => 'Good morning', 'transcript' => 'good morning',
        ])->assertOk();

        $attempt = SpeakingAttempt::first();
        $this->assertSame('good morning', $attempt->transcript);
        // Schema-level guarantee: no audio column exists at all.
        $this->assertFalse(Schema::hasColumn('speaking_attempts', 'audio_path'));
        $this->assertFalse(Schema::hasColumn('speaking_attempts', 'audio'));
    }

    public function test_speaking_xp_is_capped_per_target_per_day(): void
    {
        $payload = ['target' => 'Hello world', 'transcript' => 'hello world'];

        $first = $this->actingAs($this->user)->postJson('/api/speaking/score', $payload);
        $this->assertSame(5, $first->json('xp_awarded'));

        $second = $this->actingAs($this->user)->postJson('/api/speaking/score', $payload);
        $this->assertLessThanOrEqual(1, $second->json('xp_awarded'));
    }

    public function test_writing_submission_enforces_min_words_and_shows_model_answer(): void
    {
        $lesson = $this->makeLesson('beginner');
        $exercise = $this->makeExercise($lesson);
        $q = Question::create([
            'exercise_id' => $exercise->id, 'type' => 'writing', 'skill' => 'writing',
            'prompt' => 'Describe your day.', 'difficulty' => 'easy', 'sort_order' => 1,
            'answer_config' => ['min_words' => 10, 'model_answer' => 'I wake up at six...', 'review_points' => ['Used present simple?']],
        ]);

        $this->actingAs($this->user)->postJson('/api/writing/submit', [
            'question_id' => $q->id, 'content' => 'Too short today.',
        ])->assertUnprocessable();

        $ok = $this->actingAs($this->user)->postJson('/api/writing/submit', [
            'question_id' => $q->id,
            'content' => 'I wake up at six every morning and then I eat breakfast with my family.',
        ]);
        $ok->assertOk()->assertJsonPath('model_answer', 'I wake up at six...');
        $this->assertGreaterThan(0, $ok->json('xp_awarded'));
    }
}
