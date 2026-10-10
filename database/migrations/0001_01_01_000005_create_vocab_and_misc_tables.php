<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vocabulary', function (Blueprint $table) {
            $table->id();
            $table->string('word');
            $table->string('part_of_speech', 20)->nullable();
            $table->string('ipa', 100)->nullable();
            $table->text('definition');
            $table->text('meaning_id')->nullable(); // Indonesian meaning
            $table->text('example')->nullable();
            $table->string('category', 40)->nullable();
            $table->foreignId('cefr_level_id')->nullable()->constrained()->nullOnDelete();
            $table->string('difficulty', 10)->default('medium');
            $table->string('audio_path')->nullable();
            $table->string('usage_note')->nullable(); // e.g. US vs UK
            $table->timestamps();
            $table->softDeletes();
            $table->index('word');
            $table->index(['cefr_level_id', 'category']);
        });

        Schema::create('user_vocabulary', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vocabulary_id')->constrained('vocabulary')->cascadeOnDelete();
            // spaced repetition state (simplified SM-2)
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('incorrect_count')->default(0);
            $table->unsignedInteger('interval_days')->default(0);
            $table->decimal('ease', 4, 2)->default(2.5);
            $table->timestamp('last_reviewed_at')->nullable();
            $table->timestamp('next_review_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'vocabulary_id']);
            $table->index(['user_id', 'next_review_at']);
        });

        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon', 30)->default('star');
            $table->string('metric', 30); // lessons, exercises, words, streak, speaking, xp, level
            $table->unsignedInteger('threshold')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('achievement_id')->constrained()->cascadeOnDelete();
            $table->timestamp('earned_at');
            $table->timestamps();
            $table->unique(['user_id', 'achievement_id']);
        });

        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('bookmarkable'); // lesson or vocabulary
            $table->timestamps();
            $table->unique(['user_id', 'bookmarkable_type', 'bookmarkable_id'], 'bookmarks_unique');
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('body');
            $table->timestamps();
            $table->index(['user_id', 'lesson_id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30); // achievement, level, reminder, system
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('link')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('notes');
        Schema::dropIfExists('bookmarks');
        Schema::dropIfExists('user_achievements');
        Schema::dropIfExists('achievements');
        Schema::dropIfExists('user_vocabulary');
        Schema::dropIfExists('vocabulary');
    }
};
