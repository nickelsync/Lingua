<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'vocabulary_id', 'review_count', 'correct_count', 'incorrect_count', 'interval_days', 'ease', 'last_reviewed_at', 'next_review_at'])]
class UserVocabulary extends Model
{
    protected $table = 'user_vocabulary';

    protected function casts(): array
    {
        return [
            'ease' => 'float',
            'last_reviewed_at' => 'datetime',
            'next_review_at' => 'datetime',
        ];
    }

    public function vocabulary(): BelongsTo
    {
        return $this->belongsTo(Vocabulary::class);
    }
}
