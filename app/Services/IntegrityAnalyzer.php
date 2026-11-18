<?php

namespace App\Services;

use App\Models\AttemptAnswer;
use App\Models\AttemptInstance;
use App\Models\ExamAttempt;
use App\Models\ExamSchedule;
use App\Models\IntegrityFlag;
use App\Models\Question;
use Illuminate\Support\Facades\DB;

/**
 * Analitik integritas setelah ujian (Spesifikasi 5.11). Hasilnya daftar "PERLU DITINJAU", BUKAN vonis otomatis.
 *  1. identical_wrong : pasangan siswa dengan jawaban SALAH identik jauh di atas wajar (+ urutan & waktu menjawab mirip).
 *  2. speed           : jawaban benar dalam waktu sangat singkat untuk soal panjang.
 *  3. shared_device   : sidik perangkat sama dipakai beberapa NISN pada sesi yang sama (indikasi joki).
 */
class IntegrityAnalyzer
{
    /** Ambang minimal jawaban salah identik agar ditandai. */
    public const MIN_IDENTICAL_WRONG = 4;

    public function analyze(ExamSchedule $schedule): array
    {
        $result = DB::transaction(function () use ($schedule) {
            IntegrityFlag::query()->where('exam_schedule_id', $schedule->id)->whereNull('reviewed_at')->delete();
            $flags = array_merge(
                $this->identicalWrong($schedule),
                $this->speed($schedule),
                $this->sharedDevice($schedule),
            );
            foreach ($flags as $f) {
                IntegrityFlag::query()->create($f + ['exam_schedule_id' => $schedule->id]);
            }

            return $flags;
        });
        $schedule->forceFill(['analyzed_at' => now()])->save();

        return ['total' => count($result)];
    }

    /** @return list<array> */
    public function identicalWrong(ExamSchedule $schedule): array
    {
        $attempts = ExamAttempt::query()->where('exam_schedule_id', $schedule->id)->whereIn('status', ExamAttempt::DONE_STATUSES)->get(['id', 'student_id']);
        if ($attempts->count() < 2) {
            return [];
        }
        $answers = AttemptAnswer::query()->whereIn('exam_attempt_id', $attempts->pluck('id'))
            ->whereNotNull('answer')->get(['exam_attempt_id', 'question_id', 'answer', 'is_correct', 'answered_at', 'first_answered_sec']);
        $types = Question::query()->whereIn('id', $answers->pluck('question_id')->unique())->pluck('type', 'id');

        // Peta salah per attempt: question_id → jawaban (dinormalisasi), hanya soal objektif yang salah.
        $wrong = [];
        $order = [];
        foreach ($answers as $a) {
            $order[$a->exam_attempt_id][$a->question_id] = $a->first_answered_sec ?? 0;
            if ($a->is_correct === false && in_array($types[$a->question_id] ?? '', ['pg', 'pgk', 'bs', 'jodoh', 'isian'], true)) {
                $wrong[$a->exam_attempt_id][$a->question_id] = mb_strtolower(json_encode($a->answer, JSON_UNESCAPED_UNICODE));
            }
        }
        $byStudent = $attempts->pluck('student_id', 'id');
        $ids = array_keys($wrong);
        $pairs = [];
        $counts = [];
        for ($i = 0; $i < count($ids); $i++) {
            for ($j = $i + 1; $j < count($ids); $j++) {
                $a = $ids[$i];
                $b = $ids[$j];
                $same = 0;
                $sameQs = [];
                foreach ($wrong[$a] as $qid => $ans) {
                    if (isset($wrong[$b][$qid]) && $wrong[$b][$qid] === $ans) {
                        $same++;
                        $sameQs[] = $qid;
                    }
                }
                $counts[] = $same;
                if ($same >= self::MIN_IDENTICAL_WRONG) {
                    $pairs[] = [$a, $b, $same, $sameQs];
                }
            }
        }
        if (! $pairs) {
            return [];
        }
        $mean = array_sum($counts) / max(1, count($counts));
        $sd = sqrt(array_sum(array_map(fn ($c) => ($c - $mean) ** 2, $counts)) / max(1, count($counts)));
        $flags = [];
        foreach ($pairs as [$a, $b, $same, $qs]) {
            $z = $sd > 0 ? ($same - $mean) / $sd : $same;
            // "Jauh di atas wajar": z ≥ 2 terhadap semua pasangan, ATAU ≥ 80% jawaban salah keduanya identik
            // (kelas kecil membuat z-score kurang peka).
            $ratio = $same / max(1, min(count($wrong[$a]), count($wrong[$b])));
            if ($z < 2.0 && $ratio < 0.8) {
                continue;
            }
            // Urutan menjawab & jarak waktu pada soal yang sama-sama salah identik.
            $gaps = [];
            foreach ($qs as $q) {
                $gaps[] = abs(($order[$a][$q] ?? 0) - ($order[$b][$q] ?? 0));
            }
            sort($gaps);
            $medianGap = $gaps[(int) floor(count($gaps) / 2)] ?? null;
            $flags[] = [
                'kind' => 'identical_wrong',
                'student_ids' => [$byStudent[$a], $byStudent[$b]],
                'score' => round($z, 2),
                'detail' => ['identical_wrong' => $same, 'ratio' => round($ratio, 2), 'mean' => round($mean, 2), 'sd' => round($sd, 2), 'median_time_gap_sec' => $medianGap, 'questions' => array_slice($qs, 0, 20)],
            ];
        }

        return $flags;
    }

    /** Jawaban benar < 8 detik untuk soal panjang (> 400 karakter teks + stimulus). */
    public function speed(ExamSchedule $schedule): array
    {
        $attempts = ExamAttempt::query()->where('exam_schedule_id', $schedule->id)->whereIn('status', ExamAttempt::DONE_STATUSES)->get(['id', 'student_id']);
        $questions = Question::query()->with('stimulus:id,content')->whereIn('id', AttemptAnswer::query()->whereIn('exam_attempt_id', $attempts->pluck('id'))->pluck('question_id')->unique())->get();
        $long = $questions->filter(fn ($q) => mb_strlen(strip_tags((string) $q->content . ' ' . (string) $q->stimulus?->content)) > 400)->pluck('id')->all();
        if (! $long) {
            return [];
        }
        $flags = [];
        foreach ($attempts as $at) {
            $answers = AttemptAnswer::query()->where('exam_attempt_id', $at->id)->whereIn('question_id', $long)->where('is_correct', true)
                ->orderBy('first_answered_sec')->get(['question_id', 'first_answered_sec']);
            $fast = [];
            $prev = null;
            foreach ($answers as $a) {
                if ($a->first_answered_sec === null) {
                    continue;
                }
                $delta = $prev === null ? $a->first_answered_sec : $a->first_answered_sec - $prev;
                if ($delta >= 0 && $delta < 8) {
                    $fast[] = ['q' => $a->question_id, 'sec' => $delta];
                }
                $prev = $a->first_answered_sec;
            }
            if (count($fast) >= 3) {
                $flags[] = ['kind' => 'speed', 'student_ids' => [$at->student_id], 'score' => count($fast), 'detail' => ['fast_correct' => array_slice($fast, 0, 20), 'long_questions' => count($long)]];
            }
        }

        return $flags;
    }

    /** Sidik perangkat sama dipakai ≥ 2 NISN pada sesi yang sama. */
    public function sharedDevice(ExamSchedule $schedule): array
    {
        $rows = AttemptInstance::query()->join('exam_attempts', 'exam_attempts.id', '=', 'attempt_instances.exam_attempt_id')
            ->where('exam_attempts.exam_schedule_id', $schedule->id)->whereNotNull('attempt_instances.fingerprint')
            ->get(['attempt_instances.fingerprint', 'attempt_instances.ip', 'exam_attempts.student_id', 'attempt_instances.instance_id']);
        $flags = [];
        foreach ($rows->groupBy(fn ($r) => $r->fingerprint . '|' . $r->ip) as $key => $g) {
            $students = $g->pluck('student_id')->unique()->values()->all();
            // Fingerprint = hash UA; banyak HP sejenis bisa sama → syaratkan IP sama juga.
            if (count($students) >= 2) {
                $flags[] = ['kind' => 'shared_device', 'student_ids' => $students, 'score' => count($students), 'detail' => ['ip' => $g->first()->ip, 'instances' => $g->pluck('instance_id')->unique()->count()]];
            }
        }

        return $flags;
    }
}
