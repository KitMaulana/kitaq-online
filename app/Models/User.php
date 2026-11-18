<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/** Staf: super_admin, operator, guru, pengawas. */
class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    public const ROLES = [
        'super_admin' => 'Super Admin',
        'operator' => 'Operator',
        'guru' => 'Guru',
        'pengawas' => 'Pengawas',
    ];

    protected $fillable = [
        'name', 'username', 'email', 'password', 'is_active', 'must_change_password', 'photo',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class);
    }

    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class);
    }

    public function supervisedSchedules(): BelongsToMany
    {
        return $this->belongsToMany(ExamSchedule::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function isAdminLike(): bool
    {
        return $this->hasAnyRole(['super_admin', 'operator']);
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function roleLabel(): string
    {
        $r = $this->getRoleNames()->first();

        return self::ROLES[$r] ?? '-';
    }

    public function initials(): string
    {
        return initials($this->name);
    }
}
