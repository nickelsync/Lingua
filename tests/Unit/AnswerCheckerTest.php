<?php

namespace Tests\Unit;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Services\AnswerChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCurriculum;
use Tests\TestCase;

class AnswerCheckerTest extends TestCase
{
    use BuildsCurriculum, RefreshDatabase;

    private AnswerChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = app(AnswerChecker::class);
        $this->seedLevels();
    }

    private function question(string $type, array $config = [], array $options = []): Question
    {
        $lesson = $this->makeLesson();
        $exercise = $this->makeExercise($lesson);
        $q = Question::create([
            'exercise_id' => $exercise->id, 'type' => $type, 'skill' => 'grammar',
            'prompt' => 'Test prompt', 'answer_config' => $config ?: null, 'difficulty' => 'easy', 'sort_order' => 1,
        ]);
        foreach ($options as $i => [$text, $correct]) {
            QuestionOption::create(['question_id' => $q->id, 'text' => $text, 'is_correct' => $correct, 'sort_order' => $i + 1]);
        }

        return $q->load('options');
    }

    public function test_multiple_choice_checks_option_id(): void
    {
        $q = $this->question('multiple_choice', [], [['works', true], ['work', false]]);
        $correct = $q->options->firstWhere('is_correct', true);
        $wrong = $q->options->firstWhere('is_correct', false);

        $this->assertTrue($this->checker->check($q, $correct->id));
        $this->assertFalse($this->checker->check($q, $wrong->id));
        $this->assertFalse($this->checker->check($q, 999999));
    }

    public function test_true_false(): void
    {
        $q = $this->question('true_false', ['correct' => false]);

        $this->assertTrue($this->checker->check($q, false));
        $this->assertTrue($this->checker->check($q, 'false'));
        $this->assertFalse($this->checker->check($q, true));
    }

    public function test_text_answers_ignore_case_and_punctuation(): void
    {
        $q = $this->question('fill_blank', ['accepted' => ['I like coffee', 'I love coffee']]);

        $this->assertTrue($this->checker->check($q, 'i like coffee.'));
        $this->assertTrue($this->checker->check($q, '  I LOVE COFFEE!  '));
        $this->assertFalse($this->checker->check($q, 'I hate coffee'));
    }

    public function test_rearrange_accepts_array_or_string(): void
    {
        $q = $this->question('rearrange', ['order' => ['She', 'always', 'drinks', 'tea']]);

        $this->assertTrue($this->checker->check($q, ['she', 'always', 'drinks', 'tea']));
        $this->assertTrue($this->checker->check($q, 'She always drinks tea'));
        $this->assertFalse($this->checker->check($q, 'always She drinks tea'));
    }

    public function test_matching_requires_every_pair(): void
    {
        $q = $this->question('matching', ['pairs' => [
            ['left' => 'always', 'right' => 'selalu'],
            ['left' => 'never', 'right' => 'tidak pernah'],
        ]]);

        $this->assertTrue($this->checker->check($q, ['always' => 'selalu', 'never' => 'tidak pernah']));
        $this->assertFalse($this->checker->check($q, ['always' => 'selalu', 'never' => 'selalu']));
        $this->assertFalse($this->checker->check($q, ['always' => 'selalu']));
        $this->assertFalse($this->checker->check($q, 'not-an-array'));
    }

    public function test_speaking_passes_at_70_percent_word_accuracy(): void
    {
        $q = $this->question('speaking', ['target' => 'I would like a cup of coffee']);

        $this->assertTrue($this->checker->check($q, 'I would like a cup of coffee'));
        $this->assertTrue($this->checker->check($q, 'I would like a cup of tea')); // 6/7 words
        $this->assertFalse($this->checker->check($q, 'hello there'));
    }
}
