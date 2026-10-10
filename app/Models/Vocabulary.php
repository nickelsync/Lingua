<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['word', 'part_of_speech', 'ipa', 'definition', 'meaning_id', 'example', 'category', 'cefr_level_id', 'difficulty', 'audio_path', 'usage_note'])]
class Vocabulary extends Model
{
    use SoftDeletes;

    protected $table = 'vocabulary';

    public function level(): BelongsTo
    {
        return $this->belongsTo(CefrLevel::class, 'cefr_level_id');
    }
}
