<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'cefr_level_id', 'kind', 'question_ids', 'answers', 'started_at', 'expires_at', 'submitted_at', 'score', 'passed', 'skill_breakdown'])]
class ExamAttempt extends Model
{
    protected function casts(): array
    {
        return [
            'question_ids' => 'array',
            'answers' => 'array',
            'skill_breakdown' => 'array',
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'passed' => 'boolean',
        ];
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(CefrLevel::class, 'cefr_level_id');
    }

    public function isActive(): bool
    {
        return $this->submitted_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
