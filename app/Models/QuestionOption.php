<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['question_id', 'text', 'is_correct', 'sort_order'])]
class QuestionOption extends Model
{
    protected function casts(): array
    {
        return ['is_correct' => 'boolean'];
    }
}
