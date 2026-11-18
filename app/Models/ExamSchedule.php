<?php

namespace App\Models;

use App\Services\ExamTokenService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ExamSchedule extends Model
{
    public const STATUSES = ['scheduled' => 'Terjadwal', 'open' => 'Berlangsung', 'paused' => 'Dijeda', 'closed' => 'Ditutup'];

    protected $fillable = [
        'exam_id', 'classroom_id', 'room_id', 'exam_session_id', 'date', 'opens_at', 'closes_at', 'status',
        'token_key', 'token_required', 'ip_allowlist',
    ];

    protected $hidden = ['token_key'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'paused_at' => 'datetime',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'analyzed_at' => 'datetime',
            'token_required' => 'boolean',
            'ip_allowlist' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $s) {
            if (! $s->token_key) {
                $s->token_key = Str::random(40);
            }
        });
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function specialStudents(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)->withPivot('reason');
    }

    public function supervisors(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(MonitorAlert::class);
    }

    /** Jadwal yang boleh diikuti siswa: kelasnya atau peserta khusus. */
    public function scopeForStudent(Builder $q, Student $student): Builder
    {
        return $q->where(function ($w) use ($student) {
            $w->where('classroom_id', $student->classroom_id)
                ->orWhereHas('specialStudents', fn ($s) => $s->where('students.id', $student->id));
        });
    }

    /** Pengawas hanya melihat sesi yang ditugaskan; admin/operator semua; guru jadwal ujian mapelnya. */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->isAdminLike()) {
            return $q;
        }
        if ($user->hasRole('guru')) {
            return $q->where(function ($w) use ($user) {
                $w->whereHas('exam', fn ($e) => $e->visibleTo($user))
                    ->orWhereHas('supervisors', fn ($s) => $s->where('users.id', $user->id));
            });
        }

        return $q->whereHas('supervisors', fn ($s) => $s->where('users.id', $user->id));
    }

    public function isAccessibleBy(Student $student): bool
    {
        return ($this->classroom_id !== null && $this->classroom_id === $student->classroom_id)
            || $this->specialStudents()->whereKey($student->id)->exists();
    }

    /** Terbuka untuk dimulai: status open dan dalam jam buka/tutup. */
    public function isOpenNow(): bool
    {
        $now = now();

        return $this->status === 'open' && $now->gte($this->opens_at) && $now->lt($this->closes_at);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function tokenService(): ExamTokenService
    {
        return app(ExamTokenService::class);
    }

    public function currentToken(): string
    {
        return $this->tokenService()->current($this->token_key);
    }

    public function secondsUntilTokenRotation(): int
    {
        return $this->tokenService()->secondsUntilRotation();
    }

    public function verifyToken(string $input): bool
    {
        return $this->tokenService()->verify($input, $this->token_key, null, (int) config('kitaq.exam_token.grace_windows', 1));
    }

    /** Daftar IP/CIDR efektif (jadwal → ujian → global). @return list<string> */
    public function effectiveIpAllowlist(): array
    {
        $list = array_values(array_filter((array) ($this->ip_allowlist ?? [])));
        if ($list) {
            return $list;
        }
        if ($this->exam && $this->exam->setting('ip_restrict')) {
            return array_values(config('kitaq.location.ip_allowlist', []));
        }

        return [];
    }

    /** Peserta (kelas + khusus) — query siswa. */
    public function participantsQuery(): Builder
    {
        return Student::query()->where(function ($w) {
            $w->where('classroom_id', $this->classroom_id ?? 0)
                ->orWhereIn('id', $this->specialStudents()->pluck('students.id'));
        })->where('is_active', true);
    }

    public function title(): string
    {
        return ($this->exam?->name ?? 'Ujian') . ($this->classroom ? ' · ' . $this->classroom->name : '');
    }
}
