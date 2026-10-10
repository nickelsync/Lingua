<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['lesson_id', 'title', 'kind', 'cefr_level_id', 'skill', 'question_count', 'xp_reward', 'sort_order', 'is_published'])]
class Exercise extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(CefrLevel::class, 'cefr_level_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('sort_order');
    }
}
