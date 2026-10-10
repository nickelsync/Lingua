<?php

namespace Database\Seeders;

use App\Models\CefrLevel;
use Illuminate\Database\Seeder;

class CefrLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            ['code' => 'beginner', 'name' => 'Beginner', 'sort_order' => 1, 'has_exam' => true, 'exam_question_count' => 20, 'exam_time_limit_minutes' => 25,
                'description' => 'Foundational English: greetings, the alphabet, numbers, and essential everyday expressions.'],
            ['code' => 'a1', 'name' => 'A1 — Elementary', 'sort_order' => 2, 'has_exam' => true, 'exam_question_count' => 30, 'exam_time_limit_minutes' => 40,
                'description' => 'Understand and use familiar everyday expressions and very basic phrases.'],
            ['code' => 'a2', 'name' => 'A2 — Pre-Intermediate', 'sort_order' => 3, 'has_exam' => true, 'exam_question_count' => 30, 'exam_time_limit_minutes' => 40,
                'description' => 'Communicate in simple, routine tasks about familiar topics and activities.'],
            ['code' => 'b1', 'name' => 'B1 — Intermediate', 'sort_order' => 4, 'has_exam' => true, 'exam_question_count' => 35, 'exam_time_limit_minutes' => 50,
                'description' => 'Deal with most situations while traveling, and produce connected text on familiar topics.'],
            ['code' => 'b2', 'name' => 'B2 — Upper-Intermediate', 'sort_order' => 5, 'has_exam' => true, 'exam_question_count' => 35, 'exam_time_limit_minutes' => 50,
                'description' => 'Interact with fluency and spontaneity; understand complex text on concrete and abstract topics.'],
            ['code' => 'c1', 'name' => 'C1 — Advanced', 'sort_order' => 6, 'has_exam' => true, 'exam_question_count' => 40, 'exam_time_limit_minutes' => 60,
                'description' => 'Use language flexibly and effectively for social, academic, and professional purposes.'],
            ['code' => 'c2', 'name' => 'C2 — Proficiency', 'sort_order' => 7, 'has_exam' => true, 'exam_question_count' => 40, 'exam_time_limit_minutes' => 60,
                'description' => 'Understand virtually everything heard or read; express yourself with precision and nuance.'],
            ['code' => 'mastery', 'name' => 'English Mastery', 'sort_order' => 8, 'has_exam' => false, 'exam_question_count' => 0, 'exam_time_limit_minutes' => 0,
                'description' => 'The final state: you have completed the full CEFR journey on Lingua.'],
        ];

        foreach ($levels as $level) {
            CefrLevel::updateOrCreate(['code' => $level['code']], $level);
        }
    }
}
