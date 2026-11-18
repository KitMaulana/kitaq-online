<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnlockLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['exam_attempt_id', 'user_id', 'reason', 'reset_warnings'];

    protected function casts(): array
    {
        return ['reset_warnings' => 'boolean', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
