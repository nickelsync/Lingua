<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserVocabulary;
use App\Models\Vocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCurriculum;
use Tests\TestCase;

class VocabularyTest extends TestCase
{
    use BuildsCurriculum, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLevels();
        $this->user = User::factory()->create();
    }

    private function word(string $word = 'coffee'): Vocabulary
    {
        return Vocabulary::create([
            'word' => $word, 'part_of_speech' => 'noun', 'definition' => 'A hot drink.',
            'meaning_id' => 'kopi', 'example' => 'I drink coffee.', 'category' => 'food',
            'cefr_level_id' => $this->level('beginner')->id, 'difficulty' => 'easy',
        ]);
    }

    public function test_browse_save_and_remove_words(): void
    {
        $word = $this->word();

        $this->actingAs($this->user)->getJson('/api/vocabulary')
            ->assertOk()->assertJsonPath('data.0.word', 'coffee');

        $this->actingAs($this->user)->postJson("/api/vocabulary/{$word->id}/save")->assertCreated();
        $this->assertDatabaseHas('user_vocabulary', ['user_id' => $this->user->id, 'vocabulary_id' => $word->id]);

        // Saving twice is idempotent.
        $this->actingAs($this->user)->postJson("/api/vocabulary/{$word->id}/save")->assertOk();
        $this->assertSame(1, UserVocabulary::where('user_id', $this->user->id)->count());

        $this->actingAs($this->user)->deleteJson("/api/vocabulary/{$word->id}/save")->assertOk();
        $this->assertDatabaseMissing('user_vocabulary', ['user_id' => $this->user->id, 'vocabulary_id' => $word->id]);
    }

    public function test_review_queue_serves_due_cards_and_grades_them(): void
    {
        $word = $this->word();
        $this->actingAs($this->user)->postJson("/api/vocabulary/{$word->id}/save")->assertCreated();

        $queue = $this->actingAs($this->user)->getJson('/api/review/queue');
        $queue->assertOk()->assertJsonPath('due_count', 1)->assertJsonPath('cards.0.word.word', 'coffee');
        $entryId = $queue->json('cards.0.entry_id');

        $res = $this->actingAs($this->user)->postJson("/api/review/{$entryId}", ['quality' => 2]);
        $res->assertOk()->assertJsonPath('interval_days', 1)->assertJsonPath('due_count', 0);
        $this->assertSame(2, $res->json('xp_awarded'));

        // Reviewing the same word again today grants no duplicate first-grant XP (anti-farming reference).
        $entry = UserVocabulary::find($entryId);
        $entry->update(['next_review_at' => now()->subMinute()]);
        $res2 = $this->actingAs($this->user)->postJson("/api/review/{$entryId}", ['quality' => 2]);
        $this->assertLessThanOrEqual(1, $res2->json('xp_awarded'));
    }

    public function test_cannot_review_someone_elses_entry(): void
    {
        $word = $this->word();
        $other = User::factory()->create();
        $entry = UserVocabulary::create(['user_id' => $other->id, 'vocabulary_id' => $word->id, 'next_review_at' => now()]);

        $this->actingAs($this->user)->postJson("/api/review/{$entry->id}", ['quality' => 2])->assertForbidden();
    }

    public function test_vocabulary_filters_by_level_and_search(): void
    {
        $this->word('coffee');
        $advanced = Vocabulary::create([
            'word' => 'serendipity', 'part_of_speech' => 'noun', 'definition' => 'Happy accident.',
            'meaning_id' => 'kebetulan', 'cefr_level_id' => $this->level('c1')->id, 'difficulty' => 'hard',
        ]);

        $this->actingAs($this->user)->getJson('/api/vocabulary?level=c1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.word', 'serendipity');

        $this->actingAs($this->user)->getJson('/api/vocabulary?q=coff')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.word', 'coffee');
    }
}
