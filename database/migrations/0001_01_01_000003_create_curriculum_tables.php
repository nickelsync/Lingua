<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cefr_level_id')->constrained()->cascadeOnDelete();
            $table->string('skill', 30); // grammar, vocabulary, listening, speaking, reading, writing, pronunciation, everyday
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['cefr_level_id', 'skill']);
        });

        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('objective')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('xp_reward')->default(20);
            $table->unsignedInteger('estimated_minutes')->default(10);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // ordered content blocks inside a lesson
        Schema::create('lesson_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30); // intro, explanation, examples, vocabulary, dialogue, reading, listening, note, tip
            $table->string('title')->nullable();
            $table->text('body')->nullable();          // markdown-ish text
            $table->text('body_id')->nullable();       // optional Indonesian note
            $table->json('meta')->nullable();          // e.g. dialogue lines, audio text, examples list
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('lesson_prerequisites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prerequisite_lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->unique(['lesson_id', 'prerequisite_lesson_id']);
        });

        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('kind', 20)->default('practice'); // practice, exam_pool, placement
            $table->foreignId('cefr_level_id')->nullable()->constrained()->nullOnDelete();
            $table->string('skill', 30)->nullable();
            $table->unsignedInteger('question_count')->default(0); // 0 = all, N = random pick
            $table->unsignedInteger('xp_reward')->default(10);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['kind', 'cefr_level_id']);
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30); // multiple_choice, fill_blank, true_false, matching, rearrange, translation, typing, listening, speaking, reading
            $table->string('skill', 30)->nullable();
            $table->text('prompt');
            $table->text('passage')->nullable();       // reading passage / listening script
            $table->string('audio_text')->nullable();  // text for TTS playback
            $table->string('audio_path')->nullable();  // optional uploaded audio
            $table->text('explanation')->nullable();
            $table->text('explanation_id')->nullable(); // Indonesian explanation
            $table->json('answer_config')->nullable(); // accepted answers, pairs, token order etc.
            $table->string('difficulty', 10)->default('medium');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['exercise_id', 'type']);
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->text('text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('exercises');
        Schema::dropIfExists('lesson_prerequisites');
        Schema::dropIfExists('lesson_sections');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('courses');
    }
};
