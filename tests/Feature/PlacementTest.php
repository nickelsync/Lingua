<?php

namespace Tests\Feature;

use App\Models\ExamAttempt;
use App\Models\Exercise;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCurriculum;
use Tests\TestCase;

class PlacementTest extends TestCase
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

    private function makePlacementPool(int $written = 10, int $speaking = 2): Exercise
    {
        $pool = Exercise::create([
            'title' => 'Placement Pool', 'kind' => 'placement', 'question_count' => 0,
            'xp_reward' => 0, 'sort_order' => 1, 'is_published' => true,
        ]);
        for ($i = 0; $i < $written; $i++) {
            $this->makeMcq($pool, 'yes'.$i, ['no'.$i]);
        }
        for ($i = 0; $i < $speaking; $i++) {
            Question::create([
                'exercise_id' => $pool->id, 'type' => 'speaking', 'skill' => 'speaking',
                'prompt' => 'Say: sample sentence '.$i, 'answer_config' => ['target' => 'sample sentence '.$i],
                'difficulty' => 'medium', 'sort_order' => $i + 100,
            ]);
        }

        return $pool;
    }

    public function test_placement_mixes_written_and_speaking_questions(): void
    {
        $this->makePlacementPool();

        $res = $this->actingAs($this->user)->postJson('/api/placement/start');
        $res->assertOk();

        $attempt = ExamAttempt::where('user_id', $this->user->id)->where('kind', 'placement')->first();
        $types = Question::whereIn('id', $attempt->question_ids)->pluck('type');
        $this->assertTrue($types->contains('speaking'));
        $this->assertTrue($types->contains('multiple_choice'));
        $this->assertNull($attempt->expires_at); // untimed
    }

    public function test_perfect_placement_assigns_higher_level_and_unlocks_ladder(): void
    {
        $this->makePlacementPool(10, 0);
        $this->actingAs($this->user)->postJson('/api/placement/start')->assertOk();
        $attempt = ExamAttempt::where('user_id', $this->user->id)->where('kind', 'placement')->first();

        $answers = [];
        foreach ($attempt->question_ids as $qid) {
            $q = Question::with('options')->find($qid);
            $answers[$qid] = $q->options->firstWhere('is_correct', true)->id;
        }

        $res = $this->actingAs($this->user)->postJson("/api/exams/{$attempt->id}/submit", ['answers' => $answers]);
        $res->assertOk()->assertJsonPath('kind', 'placement')->assertJsonPath('level.code', 'b2');

        $this->user->refresh();
        $this->assertSame('b2', $this->user->currentLevel->code);
        $this->assertNotNull($this->user->placement_completed_at);
        // Everything up to B2 is unlocked.
        foreach (['beginner', 'a1', 'a2', 'b1', 'b2'] as $code) {
            $this->assertDatabaseHas('level_unlocks', ['user_id' => $this->user->id, 'cefr_level_id' => $this->level($code)->id]);
        }
        $this->assertDatabaseMissing('level_unlocks', ['user_id' => $this->user->id, 'cefr_level_id' => $this->level('c1')->id]);
    }

    public function test_blank_placement_lands_on_beginner(): void
    {
        $this->makePlacementPool(10, 0);
        $this->actingAs($this->user)->postJson('/api/placement/start')->assertOk();
        $attempt = ExamAttempt::where('user_id', $this->user->id)->where('kind', 'placement')->first();

        $this->actingAs($this->user)->postJson("/api/exams/{$attempt->id}/submit", ['answers' => []])
            ->assertOk()->assertJsonPath('level.code', 'beginner');
    }

    public function test_placement_cannot_be_retaken(): void
    {
        $this->makePlacementPool(5, 0);
        $this->actingAs($this->user)->postJson('/api/placement/start')->assertOk();
        $attempt = ExamAttempt::where('user_id', $this->user->id)->where('kind', 'placement')->first();
        $this->actingAs($this->user)->postJson("/api/exams/{$attempt->id}/submit", ['answers' => []])->assertOk();

        // actingAs holds the instance in memory; pick up placement_completed_at.
        $this->actingAs($this->user->refresh())->postJson('/api/placement/start')->assertUnprocessable();
    }

    public function test_choose_start_switches_only_among_unlocked_levels(): void
    {
        // Requires a completed placement.
        $this->actingAs($this->user)->postJson('/api/placement/choose-start', ['level_id' => $this->level('a1')->id])
            ->assertUnprocessable();

        $this->makePlacementPool(10, 0);
        $this->actingAs($this->user)->postJson('/api/placement/start')->assertOk();
        $attempt = ExamAttempt::where('user_id', $this->user->id)->where('kind', 'placement')->first();
        $answers = [];
        foreach ($attempt->question_ids as $qid) {
            $q = Question::with('options')->find($qid);
            $answers[$qid] = $q->options->firstWhere('is_correct', true)->id;
        }
        $this->actingAs($this->user)->postJson("/api/exams/{$attempt->id}/submit", ['answers' => $answers])->assertOk();
        $this->user->refresh();

        // Placed at b2: can start lower at a1…
        $this->actingAs($this->user)->postJson('/api/placement/choose-start', ['level_id' => $this->level('a1')->id])
            ->assertOk()->assertJsonPath('current_level.code', 'a1');
        // …but never at a locked level.
        $this->actingAs($this->user)->postJson('/api/placement/choose-start', ['level_id' => $this->level('c1')->id])
            ->assertForbidden();
    }
}
