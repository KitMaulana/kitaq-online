<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Notifikasi untuk pengawas (diambil lewat polling monitoring). */
class MonitorAlert extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['exam_schedule_id', 'exam_attempt_id', 'kind', 'message', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ExamSchedule::class, 'exam_schedule_id');
    }
}
