<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lesson_id', 'type', 'title', 'body', 'body_id', 'meta', 'sort_order'])]
class LessonSection extends Model
{
    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
