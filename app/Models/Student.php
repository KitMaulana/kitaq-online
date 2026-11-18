<?php

namespace App\Models;

use App\Services\Auth\StudentCredential;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Siswa. Username = NISN (10 digit), password = bcrypt(tanggal lahir DDMMYYYY).
 * Tanggal lahir asli disimpan untuk reset & identitas, DISAMARKAN di UI (lihat maskedBirthDate()).
 */
class Student extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'classroom_id', 'nisn', 'nis', 'name', 'gender', 'birth_place', 'birth_date', 'password',
        'must_change_password', 'photo', 'is_active', 'accommodations',
    ];

    protected $hidden = ['password', 'remember_token', 'birth_date', 'current_session_id'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'is_active' => 'boolean',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'accommodations' => 'array',
        ];
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function specialSchedules(): BelongsToMany
    {
        return $this->belongsToMany(ExamSchedule::class)->withPivot('reason');
    }

    /** 20-10-**** (Spesifikasi 5.1, KEAMANAN.md). */
    public function maskedBirthDate(): string
    {
        return $this->birth_date ? $this->birth_date->format('d-m-') . '****' : '-';
    }

    public function resetPasswordToBirthDate(): void
    {
        $this->password = StudentCredential::passwordFromBirthDate($this->birth_date);
        $this->failed_logins = 0;
        $this->locked_until = null;
    }

    public function isFrozen(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function accommodation(string $key, mixed $default = null): mixed
    {
        return ($this->accommodations ?? [])[$key] ?? $default;
    }

    public function initials(): string
    {
        return initials($this->name);
    }
}
