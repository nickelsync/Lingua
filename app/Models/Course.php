<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['cefr_level_id', 'skill', 'title', 'slug', 'description', 'sort_order', 'is_published'])]
class Course extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(CefrLevel::class, 'cefr_level_id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('sort_order');
    }
}
