<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'exercise_id', 'score', 'correct_count', 'question_count', 'xp_awarded'])]
class ExerciseAttempt extends Model
{
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
