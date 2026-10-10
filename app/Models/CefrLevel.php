<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'description', 'sort_order', 'exam_question_count', 'exam_time_limit_minutes', 'exam_pass_score', 'has_exam'])]
class CefrLevel extends Model
{
    protected function casts(): array
    {
        return ['has_exam' => 'boolean'];
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class)->orderBy('sort_order');
    }
}
