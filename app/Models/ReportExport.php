<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ekspor berat (Excel/PDF) yang dikerjakan antrean lewat cron. */
class ReportExport extends Model
{
    public const TYPES = [
        'recap_xlsx' => 'Rekap nilai (Excel)',
        'recap_pdf' => 'Rekap nilai (PDF)',
        'erapor_xlsx' => 'Format e-Rapor (Excel)',
        'violations_xlsx' => 'Rekap pelanggaran (Excel)',
        'students_xlsx' => 'Data siswa (Excel)',
        'item_analysis_xlsx' => 'Analisis butir (Excel)',
    ];

    protected $fillable = ['user_id', 'type', 'params', 'status', 'path', 'error'];

    protected function casts(): array
    {
        return ['params' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
