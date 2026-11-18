<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stimulus extends Model
{
    protected $table = 'stimuli';

    protected $fillable = ['question_bank_id', 'title', 'content', 'image'];

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}
