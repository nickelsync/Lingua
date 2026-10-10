<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cefr_levels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('exam_question_count')->default(30);
            $table->unsignedInteger('exam_time_limit_minutes')->default(45);
            $table->unsignedInteger('exam_pass_score')->default(75);
            $table->boolean('has_exam')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 30)->unique();
            $table->string('display_name', 60)->nullable();
            $table->string('password');
            $table->string('role', 10)->default('user');
            $table->string('timezone', 64)->default('UTC');
            $table->string('avatar_path')->nullable();
            $table->boolean('is_public')->default(false);
            $table->string('learning_goal', 40)->nullable();
            $table->string('experience_level', 40)->nullable();
            $table->string('focus_skill', 40)->nullable();
            $table->unsignedInteger('daily_goal_minutes')->nullable();
            $table->timestamp('onboarded_at')->nullable();
            $table->timestamp('placement_completed_at')->nullable();
            $table->foreignId('current_level_id')->nullable()->constrained('cefr_levels')->nullOnDelete();
            $table->unsignedInteger('xp_total')->default(0);
            $table->unsignedInteger('streak_current')->default(0);
            $table->unsignedInteger('streak_longest')->default(0);
            $table->date('last_activity_date')->nullable();
            $table->string('recovery_code')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->index('role');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('cefr_levels');
    }
};
