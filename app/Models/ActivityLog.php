<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Log aktivitas: siapa membuka kunci, mengubah nilai, mereset login, mengekspor data. */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'student_id', 'action', 'subject_type', 'subject_id', 'description', 'properties', 'ip', 'user_agent'];

    protected function casts(): array
    {
        return ['properties' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public static function record(string $action, ?string $description = null, ?Model $subject = null, array $properties = [], ?int $studentId = null): self
    {
        $req = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return self::query()->create([
            'user_id' => auth('web')->id(),
            'student_id' => $studentId,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description !== null ? mb_substr($description, 0, 255) : null,
            'properties' => $properties ?: null,
            'ip' => $req?->ip(),
            'user_agent' => $req ? substr((string) $req->userAgent(), 0, 255) : null,
        ]);
    }
}
