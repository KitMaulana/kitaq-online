<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionBank extends Model
{
    protected $fillable = ['subject_id', 'user_id', 'name', 'grade', 'description'];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('position')->orderBy('id');
    }

    public function stimuli(): HasMany
    {
        return $this->hasMany(Stimulus::class);
    }

    /** Guru hanya melihat bank soal mapel yang diampu / miliknya. */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->isAdminLike()) {
            return $q;
        }

        return $q->where(function ($w) use ($user) {
            $w->where('user_id', $user->id)->orWhereIn('subject_id', $user->subjects()->pluck('subjects.id'));
        });
    }

    public function canBeManagedBy(User $user): bool
    {
        return $user->isAdminLike() || $this->user_id === $user->id || $user->subjects()->whereKey($this->subject_id)->exists();
    }
}
