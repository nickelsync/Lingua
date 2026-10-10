<?php

namespace Tests\Unit;

use App\Services\SpeakingScorer;
use PHPUnit\Framework\TestCase;

class SpeakingScorerTest extends TestCase
{
    private SpeakingScorer $scorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scorer = new SpeakingScorer;
    }

    public function test_perfect_match_scores_100(): void
    {
        $r = $this->scorer->score('Good morning everyone', 'good morning, everyone!');
        $this->assertSame(100, $r['accuracy']);
        $this->assertSame([], $r['missing']);
        $this->assertSame([], $r['extra']);
    }

    public function test_partial_match_reports_missing_and_extra(): void
    {
        $r = $this->scorer->score('I like green tea', 'I like black coffee');
        $this->assertSame(50, $r['accuracy']); // matched: I, like
        $this->assertEqualsCanonicalizing(['green', 'tea'], $r['missing']);
        $this->assertEqualsCanonicalizing(['black', 'coffee'], $r['extra']);
    }

    public function test_word_order_matters(): void
    {
        $r = $this->scorer->score('the cat chased the dog', 'the dog chased the cat');
        $this->assertLessThan(100, $r['accuracy']);
    }

    public function test_empty_transcript_scores_zero(): void
    {
        $this->assertSame(0, $this->scorer->score('hello world', '')['accuracy']);
    }
}
