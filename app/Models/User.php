<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable([
    'username', 'display_name', 'password', 'timezone', 'avatar_path', 'is_public',
    'learning_goal', 'experience_level', 'focus_skill', 'daily_goal_minutes',
])]
#[Hidden(['password', 'remember_token', 'recovery_code'])]
class User extends Authenticatable
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_public' => 'boolean',
            'onboarded_at' => 'datetime',
            'placement_completed_at' => 'datetime',
            'suspended_at' => 'datetime',
            'last_activity_date' => 'date',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function currentLevel(): BelongsTo
    {
        return $this->belongsTo(CefrLevel::class, 'current_level_id');
    }

    public function lessonCompletions(): HasMany
    {
        return $this->hasMany(LessonCompletion::class);
    }

    public function exerciseAttempts(): HasMany
    {
        return $this->hasMany(ExerciseAttempt::class);
    }

    public function examAttempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function levelUnlocks(): HasMany
    {
        return $this->hasMany(LevelUnlock::class);
    }

    public function xpTransactions(): HasMany
    {
        return $this->hasMany(XpTransaction::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LearningActivity::class);
    }

    public function vocabulary(): HasMany
    {
        return $this->hasMany(UserVocabulary::class);
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /** Account level derived from XP: level n needs n*n*100 cumulative XP. */
    public function accountLevel(): int
    {
        return (int) floor(sqrt($this->xp_total / 100)) + 1;
    }
}
