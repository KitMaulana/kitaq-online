<?php

namespace App\Services;

use App\Models\AttemptAnswer;
use App\Models\ExamAttempt;
use App\Models\Question;

/**
 * Penilaian di server (Spesifikasi 7.4). Nilai akhir = Σ skor / Σ bobot × 100.
 *  - PG          : benar = bobot penuh.
 *  - PG Kompleks : harus tepat semua (tidak ada nilai parsial) — mencegah strategi "centang semua".
 *  - Benar/Salah : parsial, proporsional jumlah pernyataan yang benar.
 *  - Menjodohkan : parsial, proporsional pasangan yang benar.
 *  - Isian       : cocok salah satu variasi kunci (abaikan besar-kecil huruf & spasi ganda; angka 1.500,0 = 1500).
 *  - Uraian      : menunggu guru (needs_grading); nilai dihitung ulang setelah dikoreksi.
 */
class ScoringService
{
    public function score(ExamAttempt $attempt): void
    {
        $order = array_map('intval', (array) $attempt->question_order);
        $questions = Question::query()->whereIn('id', $order)->get()->keyBy('id');
        $answers = AttemptAnswer::query()->where('exam_attempt_id', $attempt->id)->get()->keyBy('question_id');

        $totalWeight = 0.0;
        $earned = 0.0;
        $correct = 0;
        $wrong = 0;
        $blank = 0;
        $pending = false;

        foreach ($order as $qid) {
            $q = $questions[$qid] ?? null;
            if (! $q) {
                continue;
            }
            $w = max(0.0, (float) $q->weight);
            $totalWeight += $w;
            $ans = $answers[$qid] ?? null;
            $value = $ans?->answer;

            if ($value === null || $value === '' || $value === []) {
                $blank++;
                if ($ans) {
                    $ans->forceFill(['is_correct' => false, 'score' => 0])->save();
                }
                continue;
            }

            if ($q->type === 'uraian') {
                if ($ans->score === null) {
                    $pending = true;
                } else {
                    $earned += min($w, (float) $ans->score);
                    $ans->score >= $w ? $correct++ : $wrong++;
                }
                continue;
            }

            $ratio = $this->ratio($q, $value);
            $score = round($w * $ratio, 2);
            $earned += $score;
            $ratio >= 1.0 ? $correct++ : $wrong++;
            $ans->forceFill(['is_correct' => $ratio >= 1.0, 'score' => $score])->save();
        }

        $attempt->forceFill([
            'score' => $totalWeight > 0 ? round($earned / $totalWeight * 100, 2) : 0,
            'needs_grading' => $pending,
            'score_detail' => [
                'correct' => $correct, 'wrong' => $wrong, 'blank' => $blank,
                'earned' => round($earned, 2), 'total_weight' => round($totalWeight, 2), 'pending_essay' => $pending,
            ],
        ])->save();
    }

    /** 0..1 untuk soal otomatis. */
    public function ratio(Question $q, mixed $value): float
    {
        $key = $q->answer_key;
        switch ($q->type) {
            case 'pg':
                return (string) $value === (string) $key ? 1.0 : 0.0;
            case 'pgk':
                $a = array_map('strval', (array) $value);
                $k = array_map('strval', (array) $key);
                sort($a);
                sort($k);

                return $a === $k ? 1.0 : 0.0;
            case 'bs':
            case 'jodoh':
                $key = (array) $key;
                if (! $key) {
                    return 0.0;
                }
                $ok = 0;
                foreach ($key as $k => $v) {
                    $given = ((array) $value)[(string) $k] ?? null;
                    if ($given !== null && ($q->type === 'bs' ? (bool) $given === (bool) $v : (string) $given === (string) $v)) {
                        $ok++;
                    }
                }

                return $ok / count($key);
            case 'isian':
                $given = $this->normalizeText((string) $value, $q->numeric);
                foreach ((array) $key as $variant) {
                    if ($given !== '' && $given === $this->normalizeText((string) $variant, $q->numeric)) {
                        return 1.0;
                    }
                }

                return 0.0;
        }

        return 0.0;
    }

    public function normalizeText(string $s, bool $numeric = false): string
    {
        $s = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s) ?? $s));
        if ($numeric) {
            $s = str_replace(' ', '', $s);
            // Format Indonesia: 1.500,5 → 1500.5 ; juga terima 1500.5
            if (preg_match('/^-?[\d.]+,\d+$/', $s) || preg_match('/^-?\d{1,3}(\.\d{3})+$/', $s)) {
                $s = str_replace(['.', ','], ['', '.'], $s);
            } else {
                $s = str_replace(',', '.', $s);
            }
            if (is_numeric($s)) {
                $s = rtrim(rtrim(number_format((float) $s, 6, '.', ''), '0'), '.');
            }
        }

        return $s;
    }
}
