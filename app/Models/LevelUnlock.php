<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'cefr_level_id', 'source', 'unlocked_at'])]
class LevelUnlock extends Model
{
    protected function casts(): array
    {
        return ['unlocked_at' => 'datetime'];
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(CefrLevel::class, 'cefr_level_id');
    }
}
