<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttemptInstance extends Model
{
    public $timestamps = false;

    protected $fillable = ['exam_attempt_id', 'instance_id', 'os', 'browser', 'device', 'standalone', 'ip', 'fingerprint', 'first_seen_at', 'last_seen_at'];

    protected function casts(): array
    {
        return ['standalone' => 'boolean', 'first_seen_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }
}
