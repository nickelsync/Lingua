<?php

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Seeder;

class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        $achievements = [
            ['code' => 'first_lesson', 'name' => 'First Lesson', 'metric' => 'lessons', 'threshold' => 1, 'icon' => 'book', 'description' => 'Complete your first lesson.'],
            ['code' => 'ten_lessons', 'name' => 'Dedicated Learner', 'metric' => 'lessons', 'threshold' => 10, 'icon' => 'book', 'description' => 'Complete 10 lessons.'],
            ['code' => 'fifty_lessons', 'name' => 'Course Crusher', 'metric' => 'lessons', 'threshold' => 50, 'icon' => 'book', 'description' => 'Complete 50 lessons.'],
            ['code' => 'first_quiz', 'name' => 'First Quiz', 'metric' => 'exercises', 'threshold' => 1, 'icon' => 'check', 'description' => 'Finish your first exercise.'],
            ['code' => 'quiz_25', 'name' => 'Practice Makes Perfect', 'metric' => 'exercises', 'threshold' => 25, 'icon' => 'check', 'description' => 'Finish 25 exercises.'],
            ['code' => 'first_words', 'name' => 'Word Collector', 'metric' => 'words', 'threshold' => 10, 'icon' => 'bookmark', 'description' => 'Save 10 words to your vocabulary.'],
            ['code' => 'hundred_words', 'name' => 'First 100 Words', 'metric' => 'words', 'threshold' => 100, 'icon' => 'bookmark', 'description' => 'Save 100 words to your vocabulary.'],
            ['code' => 'reviews_100', 'name' => 'Review Machine', 'metric' => 'reviews', 'threshold' => 100, 'icon' => 'refresh', 'description' => 'Complete 100 flashcard reviews.'],
            ['code' => 'streak_7', 'name' => '7 Day Streak', 'metric' => 'streak', 'threshold' => 7, 'icon' => 'flame', 'description' => 'Learn 7 days in a row.'],
            ['code' => 'streak_30', 'name' => '30 Day Streak', 'metric' => 'streak', 'threshold' => 30, 'icon' => 'flame', 'description' => 'Learn 30 days in a row.'],
            ['code' => 'first_speaking', 'name' => 'First Words Spoken', 'metric' => 'speaking', 'threshold' => 1, 'icon' => 'mic', 'description' => 'Complete your first speaking practice.'],
            ['code' => 'speaking_25', 'name' => 'Confident Speaker', 'metric' => 'speaking', 'threshold' => 25, 'icon' => 'mic', 'description' => 'Complete 25 speaking practices.'],
            ['code' => 'first_writing', 'name' => 'First Draft', 'metric' => 'writing', 'threshold' => 1, 'icon' => 'pen', 'description' => 'Submit your first piece of writing.'],
            ['code' => 'xp_1000', 'name' => 'Rising Star', 'metric' => 'xp', 'threshold' => 1000, 'icon' => 'star', 'description' => 'Earn 1,000 XP.'],
            ['code' => 'xp_10000', 'name' => 'XP Veteran', 'metric' => 'xp', 'threshold' => 10000, 'icon' => 'star', 'description' => 'Earn 10,000 XP.'],
            ['code' => 'level_up', 'name' => 'Level Up', 'metric' => 'levels', 'threshold' => 1, 'icon' => 'trophy', 'description' => 'Pass your first final exam and unlock a new level.'],
            ['code' => 'level_3', 'name' => 'Climbing the Ladder', 'metric' => 'levels', 'threshold' => 3, 'icon' => 'trophy', 'description' => 'Unlock 3 levels through final exams.'],
        ];

        foreach ($achievements as $achievement) {
            Achievement::updateOrCreate(['code' => $achievement['code']], $achievement);
        }
    }
}
