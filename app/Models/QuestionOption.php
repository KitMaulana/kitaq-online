<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionOption extends Model
{
    public $timestamps = false;

    protected $fillable = ['question_id', 'key', 'side', 'content', 'image', 'position'];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
