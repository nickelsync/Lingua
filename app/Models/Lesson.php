<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['module_id', 'title', 'slug', 'objective', 'sort_order', 'xp_reward', 'estimated_minutes', 'is_published'])]
class Lesson extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(LessonSection::class)->orderBy('sort_order');
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class)->orderBy('sort_order');
    }

    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(Lesson::class, 'lesson_prerequisites', 'lesson_id', 'prerequisite_lesson_id');
    }

    public function completions(): HasMany
    {
        return $this->hasMany(LessonCompletion::class);
    }
}
