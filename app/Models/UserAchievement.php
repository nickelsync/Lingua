<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'achievement_id', 'earned_at'])]
class UserAchievement extends Model
{
    protected function casts(): array
    {
        return ['earned_at' => 'datetime'];
    }

    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class);
    }
}
