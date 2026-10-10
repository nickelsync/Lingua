<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['exercise_id', 'type', 'skill', 'prompt', 'passage', 'audio_text', 'audio_path', 'explanation', 'explanation_id', 'answer_config', 'difficulty', 'sort_order'])]
class Question extends Model
{
    protected function casts(): array
    {
        return ['answer_config' => 'array'];
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('sort_order');
    }
}
