<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'question_id', 'target_text', 'transcript', 'accuracy', 'feedback'])]
class SpeakingAttempt extends Model
{
    protected function casts(): array
    {
        return ['feedback' => 'array'];
    }
}
