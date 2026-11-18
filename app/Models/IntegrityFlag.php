<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Hasil analitik integritas: "perlu ditinjau", BUKAN vonis otomatis. */
class IntegrityFlag extends Model
{
    public const KINDS = [
        'identical_wrong' => 'Jawaban salah identik',
        'speed' => 'Kecepatan tidak wajar',
        'shared_device' => 'Satu perangkat, banyak akun',
    ];

    public const VERDICTS = ['wajar' => 'Wajar / tidak ada masalah', 'perlu_tindak' => 'Perlu tindak lanjut', 'pelanggaran' => 'Terbukti pelanggaran'];

    protected $fillable = ['exam_schedule_id', 'kind', 'student_ids', 'score', 'detail', 'reviewed_by', 'verdict', 'note', 'reviewed_at'];

    protected function casts(): array
    {
        return ['student_ids' => 'array', 'detail' => 'array', 'score' => 'float', 'reviewed_at' => 'datetime'];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ExamSchedule::class, 'exam_schedule_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function students()
    {
        return Student::query()->whereIn('id', $this->student_ids ?? [])->get();
    }
}
