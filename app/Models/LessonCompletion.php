<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'lesson_id', 'first_completed_at', 'completion_count'])]
class LessonCompletion extends Model
{
    protected function casts(): array
    {
        return ['first_completed_at' => 'datetime'];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
