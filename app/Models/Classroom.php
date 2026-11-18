<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    protected $fillable = ['academic_year_id', 'name', 'grade', 'homeroom_user_id'];

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function homeroom(): BelongsTo
    {
        return $this->belongsTo(User::class, 'homeroom_user_id');
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
