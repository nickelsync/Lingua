<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\UserVocabulary;
use App\Models\Vocabulary;
use App\Services\SpacedRepetitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCurriculum;
use Tests\TestCase;

class SpacedRepetitionTest extends TestCase
{
    use BuildsCurriculum, RefreshDatabase;

    private function entry(): UserVocabulary
    {
        $this->seedLevels();
        $user = User::factory()->create();
        $word = Vocabulary::create([
            'word' => 'serendipity', 'part_of_speech' => 'noun', 'definition' => 'A happy accident.',
            'meaning_id' => 'kebetulan yang menyenangkan', 'cefr_level_id' => $this->level('c1')->id,
            'difficulty' => 'hard',
        ]);

        return UserVocabulary::create(['user_id' => $user->id, 'vocabulary_id' => $word->id, 'next_review_at' => now()]);
    }

    public function test_good_reviews_grow_the_interval(): void
    {
        $srs = app(SpacedRepetitionService::class);
        $entry = $this->entry();

        $entry = $srs->review($entry, 2);
        $this->assertSame(1, $entry->interval_days);

        $entry = $srs->review($entry, 2);
        $this->assertSame(3, $entry->interval_days);

        $entry = $srs->review($entry, 2);
        $this->assertGreaterThan(3, $entry->interval_days);
        $this->assertTrue($entry->next_review_at->isFuture());
    }

    public function test_again_resets_interval_and_lowers_ease(): void
    {
        $srs = app(SpacedRepetitionService::class);
        $entry = $this->entry();

        $entry = $srs->review($entry, 3);
        $entry = $srs->review($entry, 3);
        $easeBefore = $entry->ease;

        $entry = $srs->review($entry, 0);
        $this->assertSame(0, $entry->interval_days);
        $this->assertLessThan($easeBefore, $entry->ease);
        $this->assertSame(1, $entry->incorrect_count);
    }

    public function test_interval_is_capped_at_365_days(): void
    {
        $srs = app(SpacedRepetitionService::class);
        $entry = $this->entry();
        $entry->interval_days = 300;
        $entry->ease = 3.0;

        $entry = $srs->review($entry, 3);
        $this->assertLessThanOrEqual(365, $entry->interval_days);
    }
}
