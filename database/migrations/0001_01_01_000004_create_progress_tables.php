<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->timestamp('first_completed_at');
            $table->unsignedInteger('completion_count')->default(1);
            $table->timestamps();
            $table->unique(['user_id', 'lesson_id']);
        });

        Schema::create('exercise_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('score');        // percent 0-100
            $table->unsignedInteger('correct_count');
            $table->unsignedInteger('question_count');
            $table->unsignedInteger('xp_awarded')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'exercise_id']);
        });

        Schema::create('question_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_attempt_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('exam_attempt_id')->nullable();
            $table->text('answer')->nullable();
            $table->boolean('is_correct');
            $table->timestamps();
            $table->index(['user_id', 'question_id']);
        });

        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cefr_level_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 15)->default('final'); // final, placement
            $table->json('question_ids');                 // frozen randomized selection
            $table->json('answers')->nullable();          // question_id => raw answer
            $table->timestamp('started_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('score')->nullable();
            $table->boolean('passed')->nullable();
            $table->json('skill_breakdown')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'cefr_level_id', 'kind']);
        });

        Schema::create('level_unlocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cefr_level_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20)->default('exam'); // exam, placement, initial
            $table->timestamp('unlocked_at');
            $table->timestamps();
            $table->unique(['user_id', 'cefr_level_id']);
        });

        Schema::create('xp_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('amount');
            $table->string('source', 30);    // lesson, exercise, exam, vocab_review, speaking, writing
            $table->string('reference', 60)->nullable(); // e.g. lesson:12
            $table->timestamps();
            $table->index(['user_id', 'source', 'reference']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('learning_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30); // lesson, exercise, exam, vocab_review, speaking, writing
            $table->string('subject')->nullable();
            $table->unsignedInteger('xp')->default(0);
            $table->date('local_date'); // user-timezone date, for streaks & graphs
            $table->timestamps();
            $table->index(['user_id', 'local_date']);
        });

        Schema::create('speaking_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->nullable()->constrained()->nullOnDelete();
            $table->text('target_text');
            $table->text('transcript')->nullable();
            $table->unsignedInteger('accuracy')->nullable(); // percent word accuracy
            $table->json('feedback')->nullable();            // missing/extra words
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('writing_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->nullable()->constrained()->nullOnDelete();
            $table->text('prompt');
            $table->text('content');
            $table->unsignedInteger('word_count')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('writing_submissions');
        Schema::dropIfExists('speaking_attempts');
        Schema::dropIfExists('learning_activities');
        Schema::dropIfExists('xp_transactions');
        Schema::dropIfExists('level_unlocks');
        Schema::dropIfExists('exam_attempts');
        Schema::dropIfExists('question_attempts');
        Schema::dropIfExists('exercise_attempts');
        Schema::dropIfExists('lesson_completions');
    }
};
