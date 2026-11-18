<?php

namespace App\Models;

use App\Services\Shield\ShieldState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamAttempt extends Model
{
    public const STATUS_LABELS = [
        'not_started' => 'Belum masuk',
        'in_progress' => 'Mengerjakan',
        'warned' => 'Peringatan',
        'locked' => 'Terkunci',
        'submitted' => 'Selesai',
        'force_submitted' => 'Dikumpulkan paksa',
    ];

    public const ACTIVE_STATUSES = ['in_progress', 'warned', 'locked'];
    public const DONE_STATUSES = ['submitted', 'force_submitted'];

    protected $fillable = [
        'exam_schedule_id', 'student_id', 'status', 'shield_status', 'warning_count', 'last_incident_at', 'lock_reason',
        'locked_at', 'package', 'question_order', 'option_order', 'started_at', 'deadline_at', 'submitted_at', 'extra_minutes',
        'active_instance_id', 'heartbeat_nonce', 'last_heartbeat_at', 'gap_reported_at', 'last_event_seq', 'ip_address',
        'user_agent', 'device_info', 'score', 'score_detail', 'needs_grading',
    ];

    protected $hidden = ['option_order', 'heartbeat_nonce'];

    protected function casts(): array
    {
        return [
            'question_order' => 'array',
            'option_order' => 'array',
            'device_info' => 'array',
            'score_detail' => 'array',
            'started_at' => 'datetime',
            'deadline_at' => 'datetime',
            'submitted_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'gap_reported_at' => 'datetime',
            'locked_at' => 'datetime',
            'score' => 'float',
            'needs_grading' => 'boolean',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ExamSchedule::class, 'exam_schedule_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShieldEvent::class);
    }

    public function instances(): HasMany
    {
        return $this->hasMany(AttemptInstance::class);
    }

    public function unlockLogs(): HasMany
    {
        return $this->hasMany(UnlockLog::class);
    }

    public function commands(): HasMany
    {
        return $this->hasMany(AttemptCommand::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function isDone(): bool
    {
        return in_array($this->status, self::DONE_STATUSES, true);
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    public function shieldState(): ShieldState
    {
        return new ShieldState(
            $this->shield_status ?: ShieldState::ACTIVE,
            (int) $this->warning_count,
            $this->last_incident_at !== null ? (int) $this->last_incident_at : null,
            $this->lock_reason,
        );
    }

    public function applyShieldState(ShieldState $state): void
    {
        $this->shield_status = $state->status;
        $this->warning_count = $state->warnings;
        $this->last_incident_at = $state->lastIncidentAt;
        $this->lock_reason = $state->lockReason;
        if ($this->isDone()) {
            return;
        }
        $this->status = match ($state->status) {
            ShieldState::LOCKED => 'locked',
            ShieldState::WARNED => 'warned',
            default => 'in_progress',
        };
    }

    public function remainingSeconds(): int
    {
        if (! $this->deadline_at || $this->isDone()) {
            return 0;
        }

        return max(0, (int) floor(now()->diffInSeconds($this->deadline_at, false)));
    }

    public function questionCount(): int
    {
        return count($this->question_order ?? []);
    }

    /** Status untuk kartu monitoring (termasuk "terputus"). */
    public function monitorStatus(?int $gapSec = null): string
    {
        if ($this->isDone()) {
            return 'submitted';
        }
        if ($this->status === 'not_started' || ! $this->started_at) {
            return 'not_started';
        }
        if ($this->status === 'locked') {
            return 'locked';
        }
        $gapSec = $gapSec ?? (int) config('kitaq.shield.heartbeat_gap_sec', 60);
        if (! $this->last_heartbeat_at || $this->last_heartbeat_at->lt(now()->subSeconds($gapSec))) {
            return 'disconnected';
        }

        return $this->status === 'warned' ? 'warned' : 'in_progress';
    }
}
