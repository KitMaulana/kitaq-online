<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Soal. Format answer_key (kunci ASLI, tidak pernah dikirim ke siswa):
 *  - pg     : "A"
 *  - pgk    : ["A","C"]
 *  - bs     : {"1": true, "2": false}          (kunci pernyataan → benar/salah)
 *  - jodoh  : {"1": "b", "2": "a"}             (kiri → kanan)
 *  - isian  : ["Jakarta", "DKI Jakarta"]       (beberapa variasi diterima)
 *  - uraian : null                             (dinilai guru)
 */
class Question extends Model
{
    public const TYPES = [
        'pg' => 'Pilihan Ganda',
        'pgk' => 'PG Kompleks',
        'bs' => 'Benar/Salah',
        'jodoh' => 'Menjodohkan',
        'isian' => 'Isian Singkat',
        'uraian' => 'Uraian',
    ];

    protected $fillable = [
        'question_bank_id', 'stimulus_id', 'type', 'content', 'image', 'answer_key', 'weight', 'difficulty',
        'cognitive_level', 'explanation', 'numeric', 'max_length', 'audio', 'audio_limit', 'package', 'position',
    ];

    protected $hidden = ['answer_key', 'explanation'];

    protected function casts(): array
    {
        return [
            'answer_key' => 'array',
            'weight' => 'float',
            'numeric' => 'boolean',
        ];
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    public function stimulus(): BelongsTo
    {
        return $this->belongsTo(Stimulus::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('position')->orderBy('id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function isAutoGraded(): bool
    {
        return $this->type !== 'uraian';
    }

    public function plainText(int $limit = 120): string
    {
        return Str::limit(trim(html_entity_decode(strip_tags((string) $this->content))), $limit);
    }

    public function maxLength(): int
    {
        return $this->max_length ?: ($this->type === 'uraian' ? 3000 : 120);
    }
}
