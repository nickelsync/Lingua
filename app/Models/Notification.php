<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'type', 'title', 'body', 'link', 'read_at'])]
class Notification extends Model
{
    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
