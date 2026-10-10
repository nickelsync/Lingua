<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CefrLevelSeeder::class,
            AchievementSeeder::class,
            VocabularySeeder::class,
        ]);

        // Curriculum content per level
        $builder = new CurriculumBuilder;
        foreach (['beginner', 'a1', 'a2', 'b1', 'b2', 'c1', 'c2'] as $code) {
            $path = database_path("seeders/content/{$code}.php");
            if (file_exists($path)) {
                $builder->build(require $path);
            }
        }

        // Placement assessment pool (50 MC + 10 speaking)
        $placementPath = database_path('seeders/content/placement.php');
        if (file_exists($placementPath)) {
            $builder->buildPlacementPool(require $placementPath);
        }

        $this->call(AdminUserSeeder::class);
    }
}
