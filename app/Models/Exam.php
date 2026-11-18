<?php

namespace App\Models;

use App\Services\Shield\ViolationPolicy;
use App\Support\ExamSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    public const TYPES = ['UH' => 'Ulangan Harian', 'PTS' => 'Penilaian Tengah Semester', 'PAS' => 'Penilaian Akhir Semester', 'PAT' => 'Penilaian Akhir Tahun', 'TO' => 'Try Out', 'ASAJ' => 'Asesmen Sekolah', 'LAIN' => 'Lainnya'];

    protected $fillable = ['name', 'type', 'subject_id', 'question_bank_id', 'user_id', 'question_count', 'duration_minutes', 'kkm', 'settings'];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ExamSchedule::class);
    }

    /** @return array<string,mixed> */
    public function resolvedSettings(): array
    {
        return ExamSettings::resolve($this->settings);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->resolvedSettings()[$key] ?? $default;
    }

    public function policy(): ViolationPolicy
    {
        $s = $this->resolvedSettings();
        $overrides = $s['overrides'];
        // Putus koneksi: "hanya catat" (default) atau "kunci setelah N detik" (dievaluasi job DetectHeartbeatGaps).
        $overrides['offline_lock'] = $s['offline_mode'] === 'lock' ? ViolationPolicy::LOCK : ViolationPolicy::LOG;
        $overrides['heartbeat_gap'] = $overrides['heartbeat_gap'] ?? ViolationPolicy::LOG;

        return ViolationPolicy::fromPreset($s['preset'], $overrides, $s['max_warnings'], (int) config('kitaq.shield.merge_window_sec', 3));
    }

    /** Jumlah soal yang benar-benar diujikan. */
    public function effectiveQuestionCount(): int
    {
        $total = Question::query()->where('question_bank_id', $this->question_bank_id)->count();

        return $this->question_count > 0 ? min($this->question_count, $total) : $total;
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->isAdminLike()) {
            return $q;
        }

        return $q->where(function ($w) use ($user) {
            $w->where('user_id', $user->id)->orWhereIn('subject_id', $user->subjects()->pluck('subjects.id'));
        });
    }
}
