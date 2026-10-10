<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'question_id', 'prompt', 'content', 'word_count'])]
class WritingSubmission extends Model {}
