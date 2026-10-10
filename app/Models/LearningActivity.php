<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'type', 'subject', 'xp', 'local_date'])]
class LearningActivity extends Model
{
    protected function casts(): array
    {
        return ['local_date' => 'date'];
    }
}
